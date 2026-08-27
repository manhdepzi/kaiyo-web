<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Growth\Contracts\MerchantDestination;
use App\Modules\Growth\Data\MerchantFeedItem;
use App\Modules\Growth\Infrastructure\Persistence\Models\MerchantFeedBatch;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Pricing\Application\Services\DatabasePricingResolver;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ProcessMerchantFeedBatch
{
    public function __construct(
        private MerchantDestination $destination,
        private DatabasePricingResolver $pricing,
    ) {}

    public function execute(MerchantFeedBatch $batch): MerchantFeedBatch
    {
        $claimed = DB::transaction(function () use ($batch): bool {
            $locked = MerchantFeedBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state === 'completed' || $locked->state === 'running') {
                return false;
            }
            $locked->forceFill(['state' => 'running', 'started_at' => $locked->started_at ?? now(), 'completed_at' => null])->save();

            return true;
        }, 3);
        if (! $claimed) {
            return $batch->refresh();
        }

        $variants = Variant::query()->with(['product.category', 'product.brand'])
            ->where('variants.status', 'active')->whereNull('variants.deleted_at')
            ->whereHas('product', fn (Builder $query) => $query->where('products.status', 'active')->whereNull('products.deleted_at')
                ->whereHas('category', fn (Builder $category) => $category->where('categories.status', 'active')->whereNull('categories.deleted_at'))
                ->where(fn (Builder $brand) => $brand->whereNull('products.brand_id')->orWhereHas('brand', fn (Builder $active) => $active->where('brands.status', 'active')->whereNull('brands.deleted_at'))))
            ->orderBy('variants.id')->get();

        foreach ($variants as $variant) {
            $this->processItem($batch, $variant);
        }

        $counts = DB::table('merchant_feed_item_results')->where('merchant_feed_batch_id', $batch->getKey())
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw("SUM(CASE WHEN outcome = 'succeeded' THEN 1 ELSE 0 END) AS succeeded_count")
            ->selectRaw("SUM(CASE WHEN outcome = 'failed' THEN 1 ELSE 0 END) AS failed_count")
            ->first();
        $total = (int) ($counts->total_count ?? 0);
        $succeeded = (int) ($counts->succeeded_count ?? 0);
        $failed = (int) ($counts->failed_count ?? 0);
        $state = $failed === 0 ? 'completed' : ($succeeded === 0 ? 'failed' : 'partial');
        $batch->forceFill([
            'state' => $state,
            'total_count' => $total,
            'succeeded_count' => $succeeded,
            'failed_count' => $failed,
            'completed_at' => now(),
        ])->save();

        return $batch->refresh();
    }

    private function processItem(MerchantFeedBatch $batch, Variant $variant): void
    {
        $product = $variant->product;
        $baseRevision = (string) $variant->lock_version;
        try {
            if ($product === null) {
                throw new DomainException('Merchant source product is unavailable.');
            }
            $baseRevision = implode('|', [$product->lock_version, $variant->lock_version]);
            $pricing = $this->pricing->resolve($variant, '1.0000');
            $available = $this->availableQuantity((int) $variant->getKey());
            $sourceRevision = implode('|', [$baseRevision, $pricing->sourceReference, $pricing->unitAmount, $available]);
            $sourceHash = hash('sha256', $sourceRevision, true);
            $existing = DB::table('merchant_feed_item_results')
                ->where('merchant_feed_batch_id', $batch->getKey())->where('variant_id', $variant->getKey())->first();
            if ($existing !== null && $existing->outcome === 'succeeded' && hash_equals((string) $existing->source_revision_hash, $sourceHash)) {
                return;
            }
            $item = new MerchantFeedItem(
                hash('sha256', $batch->public_id.'|'.$variant->public_id.'|'.bin2hex($sourceHash)),
                $product->public_id,
                $variant->public_id,
                $variant->sku,
                (string) $product->name,
                route('public.product', $product->slug),
                $pricing->currency,
                $pricing->unitAmount,
                $available,
                bin2hex($sourceHash),
            );
            $payloadHash = hash('sha256', json_encode($item->payload(), JSON_THROW_ON_ERROR), true);
            $result = $this->destination->publish($item);
            $this->storeResult(
                $batch,
                $variant,
                $sourceHash,
                $payloadHash,
                $result->succeeded ? 'succeeded' : 'failed',
                $result->destinationReference,
                $result->errorCode,
            );
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof DomainException ? 'source_unavailable' : 'destination_failure';
            $this->storeResult($batch, $variant, hash('sha256', $baseRevision, true), null, 'failed', null, $errorCode);
        }
    }

    private function availableQuantity(int $variantId): string
    {
        $units = StockBalance::query()->where('variant_id', $variantId)->get()
            ->sum(fn (StockBalance $balance): int => InventoryQuantity::from($balance->availableQuantity())->units);

        return InventoryQuantity::fromUnits((int) $units)->decimal();
    }

    private function storeResult(MerchantFeedBatch $batch, Variant $variant, string $sourceHash, ?string $payloadHash, string $outcome, ?string $reference, ?string $errorCode): void
    {
        if (($outcome === 'succeeded') === ($reference === null) || ($outcome === 'failed') === ($errorCode === null)) {
            throw new DomainException('Merchant destination returned an invalid outcome.');
        }
        DB::transaction(function () use ($batch, $variant, $sourceHash, $payloadHash, $outcome, $reference, $errorCode): void {
            $existing = DB::table('merchant_feed_item_results')
                ->where('merchant_feed_batch_id', $batch->getKey())->where('variant_id', $variant->getKey())
                ->lockForUpdate()->first();
            $values = [
                'source_revision_hash' => $sourceHash,
                'payload_hash' => $payloadHash,
                'outcome' => $outcome,
                'destination_reference' => $reference === null ? null : mb_substr($reference, 0, 255),
                'error_code' => $errorCode === null ? null : mb_substr($errorCode, 0, 100),
                'attempt_count' => (int) ($existing->attempt_count ?? 0) + 1,
                'last_attempted_at' => now(),
                'updated_at' => now(),
            ];
            if ($existing === null) {
                DB::table('merchant_feed_item_results')->insert([
                    'merchant_feed_batch_id' => $batch->getKey(),
                    'variant_id' => $variant->getKey(),
                    ...$values,
                    'created_at' => now(),
                ]);

                return;
            }
            DB::table('merchant_feed_item_results')->where('id', $existing->id)->update($values);
        }, 3);
    }
}
