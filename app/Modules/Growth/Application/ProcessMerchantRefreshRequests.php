<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Growth\Contracts\MerchantProjectionDestination;
use App\Modules\Growth\Data\MerchantProjectionChange;
use App\Modules\Growth\Data\MerchantProjectionSource;
use App\Modules\Growth\Data\MerchantPublishResult;
use App\Modules\Growth\Data\MerchantRefreshRequestData;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Pricing\Application\Services\DatabasePricingResolver;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ProcessMerchantRefreshRequests
{
    private const MAX_ATTEMPTS = 5;

    private const STALE_LEASE_MINUTES = 30;

    public function __construct(
        private MerchantProjectionDestination $destination,
        private DatabasePricingResolver $pricing,
    ) {}

    public function execute(int $limit = 25): int
    {
        if ($limit < 1 || $limit > 100) {
            throw new DomainException('Merchant refresh processing limit must be between 1 and 100.');
        }

        $ids = DB::table('merchant_feed_refresh_requests')
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('state', 'pending')->where('available_at', '<=', now());
                })->orWhere(function ($stale): void {
                    $stale->where('state', 'processing')
                        ->where('last_attempted_at', '<=', now()->subMinutes(self::STALE_LEASE_MINUTES));
                });
            })
            ->orderBy('id')->limit($limit)->pluck('id')->map(static fn (mixed $id): int => (int) $id);
        /** @var array<string, MerchantPublishResult> $coalesced */
        $coalesced = [];
        $processed = 0;
        foreach ($ids as $id) {
            $request = $this->claim($id);
            if ($request === null) {
                continue;
            }
            $this->process($request, $coalesced);
            $processed++;
        }

        return $processed;
    }

    private function claim(int $id): ?MerchantRefreshRequestData
    {
        return DB::transaction(function () use ($id): ?object {
            $row = DB::table('merchant_feed_refresh_requests')->where('id', $id)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }
            $values = (array) $row;
            $pending = ($values['state'] ?? null) === 'pending' && ! now()->isBefore($values['available_at'] ?? null);
            $stale = ($values['state'] ?? null) === 'processing'
                && ! now()->subMinutes(self::STALE_LEASE_MINUTES)->isBefore($values['last_attempted_at'] ?? null);
            if (! $pending && ! $stale) {
                return null;
            }
            $request = MerchantRefreshRequestData::fromDatabaseRow($values);
            DB::table('merchant_feed_refresh_requests')->where('id', $id)->update([
                'state' => 'processing',
                'attempt_count' => $request->attemptCount + 1,
                'last_attempted_at' => now(),
                'last_error_code' => null,
                'updated_at' => now(),
            ]);

            return MerchantRefreshRequestData::fromDatabaseRow((array) DB::table('merchant_feed_refresh_requests')->where('id', $id)->firstOrFail());
        }, 3);
    }

    /** @param array<string, MerchantPublishResult> $coalesced */
    private function process(MerchantRefreshRequestData $request, array &$coalesced): void
    {
        try {
            $variantIds = $this->variantIds($request);
            foreach ($variantIds as $variantId) {
                $this->processVariant($request, $variantId, $coalesced);
            }
            $counts = DB::table('merchant_feed_refresh_results')
                ->where('merchant_feed_refresh_request_id', $request->id)
                ->selectRaw('COUNT(*) AS total_count')
                ->selectRaw("SUM(CASE WHEN outcome = 'succeeded' THEN 1 ELSE 0 END) AS succeeded_count")
                ->selectRaw("SUM(CASE WHEN outcome = 'failed' THEN 1 ELSE 0 END) AS failed_count")
                ->first();
            $total = (int) ($counts->total_count ?? 0);
            $succeeded = (int) ($counts->succeeded_count ?? 0);
            $failed = (int) ($counts->failed_count ?? 0);
            $this->finish($request, $total, $succeeded, $failed, $failed === 0 ? null : 'destination_failure');
        } catch (Throwable $exception) {
            $this->finish($request, 0, 0, 1, $exception instanceof DomainException ? 'source_unavailable' : 'processor_failure');
        }
    }

    /** @return list<int> */
    private function variantIds(MerchantRefreshRequestData $request): array
    {
        $query = DB::table('variants')->join('products', 'products.id', '=', 'variants.product_id');
        match ($request->scopeType) {
            'variant' => $query->where('variants.public_id', $request->scopePublicId),
            'product' => $query->where('products.public_id', $request->scopePublicId),
            'category' => $query->join('categories', 'categories.id', '=', 'products.primary_category_id')
                ->where('categories.public_id', $request->scopePublicId),
            'brand' => $query->join('brands', 'brands.id', '=', 'products.brand_id')
                ->where('brands.public_id', $request->scopePublicId),
            default => throw new DomainException('Merchant refresh scope is invalid.'),
        };

        return array_values($query->orderBy('variants.id')->pluck('variants.id')->map(static fn (mixed $id): int => (int) $id)->all());
    }

    /** @param array<string, MerchantPublishResult> $coalesced */
    private function processVariant(MerchantRefreshRequestData $request, int $variantId, array &$coalesced): void
    {
        $source = $this->sourceRow($variantId);
        $operation = $this->isSellable($source) ? 'upsert' : 'remove';
        $change = $this->change($source, $operation);
        $sourceHash = hex2bin($change->sourceRevision);
        if ($sourceHash === false) {
            throw new DomainException('Merchant source revision is invalid.');
        }
        $existing = DB::table('merchant_feed_refresh_results')
            ->where('merchant_feed_refresh_request_id', $request->id)->where('variant_id', $variantId)->first();
        if ($existing !== null && $existing->outcome === 'succeeded' && $existing->operation === $operation
            && is_string($existing->source_revision_hash) && hash_equals($existing->source_revision_hash, $sourceHash)) {
            return;
        }

        $result = $coalesced[$change->idempotencyKey] ??= $this->destination->apply($change);
        if (($result->succeeded && $result->destinationReference === null) || (! $result->succeeded && $result->errorCode === null)) {
            throw new DomainException('Merchant destination returned an invalid outcome.');
        }
        $payloadHash = hash('sha256', json_encode($change->payload(), JSON_THROW_ON_ERROR), true);
        $this->storeResult($request, $variantId, $operation, $sourceHash, $payloadHash, $result);
    }

    private function sourceRow(int $variantId): MerchantProjectionSource
    {
        $row = DB::table('variants')->join('products', 'products.id', '=', 'variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.primary_category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('variants.id', $variantId)
            ->select(['variants.id', 'variants.public_id AS variant_public_id', 'variants.sku', 'variants.status AS variant_status',
                'variants.lock_version AS variant_version', 'variants.deleted_at AS variant_deleted_at',
                'products.public_id AS product_public_id', 'products.name', 'products.slug', 'products.status AS product_status',
                'products.lock_version AS product_version', 'products.deleted_at AS product_deleted_at',
                'categories.status AS category_status', 'categories.lock_version AS category_version', 'categories.deleted_at AS category_deleted_at',
                'brands.status AS brand_status', 'brands.lock_version AS brand_version', 'brands.deleted_at AS brand_deleted_at'])
            ->firstOrFail();

        return MerchantProjectionSource::fromDatabaseRow((array) $row);
    }

    private function isSellable(MerchantProjectionSource $source): bool
    {
        return $source->variantStatus === 'active' && $source->variantDeletedAt === null
            && $source->productStatus === 'active' && $source->productDeletedAt === null
            && $source->categoryStatus === 'active' && $source->categoryDeletedAt === null
            && ($source->brandStatus === null || ($source->brandStatus === 'active' && $source->brandDeletedAt === null));
    }

    private function change(MerchantProjectionSource $source, string $operation): MerchantProjectionChange
    {
        $revisionParts = [$operation, $source->variantVersion, $source->productVersion, $source->categoryVersion,
            $source->brandVersion ?? 'none', $source->variantDeletedAt ?? '', $source->productDeletedAt ?? '',
            $source->categoryDeletedAt ?? '', $source->brandDeletedAt ?? ''];
        if ($operation === 'remove') {
            $revision = hash('sha256', implode('|', $revisionParts));

            return new MerchantProjectionChange(hash('sha256', 'merchant-refresh-v1|'.$source->variantPublicId.'|'.$revision),
                'remove', $source->productPublicId, $source->variantPublicId, null, null, null, null, null, null, $revision);
        }

        $variant = Variant::query()->with(['product.category', 'product.brand'])->whereKey($source->id)->firstOrFail();
        $price = $this->pricing->resolve($variant, '1.0000');
        $available = $this->availableQuantity((int) $source->id);
        $revisionParts = [...$revisionParts, $price->sourceReference, $price->unitAmount, $available];
        $revision = hash('sha256', implode('|', $revisionParts));

        return new MerchantProjectionChange(hash('sha256', 'merchant-refresh-v1|'.$source->variantPublicId.'|'.$revision),
            'upsert', $source->productPublicId, $source->variantPublicId, $source->sku, $source->name,
            route('public.product', $source->slug), $price->currency, $price->unitAmount, $available, $revision);
    }

    private function availableQuantity(int $variantId): string
    {
        $units = StockBalance::query()->where('variant_id', $variantId)->get()
            ->sum(fn (StockBalance $balance): int => InventoryQuantity::from($balance->availableQuantity())->units);

        return InventoryQuantity::fromUnits((int) $units)->decimal();
    }

    private function storeResult(MerchantRefreshRequestData $request, int $variantId, string $operation, string $sourceHash, string $payloadHash, MerchantPublishResult $result): void
    {
        DB::transaction(function () use ($request, $variantId, $operation, $sourceHash, $payloadHash, $result): void {
            $existing = DB::table('merchant_feed_refresh_results')->where('merchant_feed_refresh_request_id', $request->id)
                ->where('variant_id', $variantId)->lockForUpdate()->first();
            $values = ['operation' => $operation, 'source_revision_hash' => $sourceHash, 'payload_hash' => $payloadHash,
                'outcome' => $result->succeeded ? 'succeeded' : 'failed',
                'destination_reference' => $result->destinationReference === null ? null : mb_substr($result->destinationReference, 0, 255),
                'error_code' => $result->errorCode === null ? null : mb_substr($result->errorCode, 0, 100),
                'attempt_count' => (int) ($existing->attempt_count ?? 0) + 1, 'last_attempted_at' => now(), 'updated_at' => now()];
            if ($existing === null) {
                DB::table('merchant_feed_refresh_results')->insert(['merchant_feed_refresh_request_id' => $request->id,
                    'variant_id' => $variantId, ...$values, 'created_at' => now()]);
            } else {
                DB::table('merchant_feed_refresh_results')->where('id', $existing->id)->update($values);
            }
        }, 3);
    }

    private function finish(MerchantRefreshRequestData $request, int $total, int $succeeded, int $failed, ?string $errorCode): void
    {
        $complete = $failed === 0;
        $dead = ! $complete && $request->attemptCount >= self::MAX_ATTEMPTS;
        DB::table('merchant_feed_refresh_requests')->where('id', $request->id)->update([
            'state' => $complete ? 'completed' : ($dead ? 'dead' : 'pending'),
            'total_count' => $total,
            'succeeded_count' => $succeeded,
            'failed_count' => $failed,
            'last_error_code' => $errorCode,
            'available_at' => $complete || $dead ? now() : now()->addMinutes(min(2 ** $request->attemptCount, 60)),
            'completed_at' => $complete || $dead ? now() : null,
            'updated_at' => now(),
        ]);
    }
}
