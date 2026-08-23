<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Quotation\Application\Data\CreateQuotationCommand;
use App\Modules\Quotation\Application\Services\QuotationRevisionBuilder;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteLine;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class CreateQuotationRevision
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private QuotationRevisionBuilder $revisions,
    ) {}

    /**
     * Creates a fully recalculated revision when products, quantities, negotiated
     * prices, addresses, shipping, validity, or commercial terms change.
     */
    public function replace(QuoteRevision $source, CreateQuotationCommand $command, UserAccount $actor): QuoteRevision
    {
        if ($command->proposer?->getKey() !== $actor->getKey()) {
            throw new AuthorizationException('Quotation revision actor evidence is invalid.');
        }
        $quote = Quote::query()->findOrFail($source->quote_id);
        $this->authorize($quote, $actor);
        $this->assertSameOwner($quote, $command);
        $hash = hash('sha256', $source->getKey().$command->requestHash(), true);
        $existing = $this->existing($command->operationKey, $hash);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($source, $command, $hash): QuoteRevision {
                $quote = Quote::query()->whereKey($source->quote_id)->lockForUpdate()->firstOrFail();
                $locked = QuoteRevision::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
                $existing = $this->existing($command->operationKey, $hash, true);
                if ($existing !== null) {
                    return $existing;
                }
                $this->assertRevisable($quote, $locked);
                $this->assertSameOwner($quote, $command);
                $revision = $this->revisions->build($quote, $command, $locked->revision_no + 1);
                $quote->forceFill(['current_revision_id' => $revision->getKey(), 'lock_version' => $quote->lock_version + 1])->save();
                DB::table('quote_operations')->insert([
                    'operation_key' => $command->operationKey, 'request_hash' => $hash, 'quote_revision_id' => $revision->getKey(),
                    'action' => 'revise', 'result_state' => 'draft', 'result_version' => 0, 'created_at' => now(),
                ]);

                return $revision;
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existing($command->operationKey, $hash);
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    /** @param array<string, bool|int|string|null> $commercialTerms */
    public function execute(QuoteRevision $source, array $commercialTerms, int $validityDays, string $operationKey, UserAccount $actor): QuoteRevision
    {
        if ($validityDays < 1 || $validityDays > (int) config('quotation.maximum_validity_days') || trim($operationKey) === '' || strlen($operationKey) > 100) {
            throw new DomainException('Quotation revision input is invalid.');
        }
        $quote = Quote::query()->findOrFail($source->quote_id);
        $this->authorize($quote, $actor);
        $hash = hash('sha256', json_encode([$source->getKey(), bin2hex($source->integrity_hash), $commercialTerms, $validityDays, $actor->public_id], JSON_THROW_ON_ERROR), true);
        $existing = $this->existing($operationKey, $hash);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($source, $commercialTerms, $validityDays, $operationKey, $actor, $hash): QuoteRevision {
                $quote = Quote::query()->whereKey($source->quote_id)->lockForUpdate()->firstOrFail();
                $locked = QuoteRevision::query()->whereKey($source->getKey())->with('lines')->lockForUpdate()->firstOrFail();
                $existing = $this->existing($operationKey, $hash, true);
                if ($existing !== null) {
                    return $existing;
                }
                $this->assertRevisable($quote, $locked);
                if ($locked->lines->isEmpty()) {
                    throw new DomainException('Quotation revision has no immutable line evidence.');
                }
                $revisionNo = $locked->revision_no + 1;
                $integrity = hash('sha256', json_encode([
                    'quote' => $quote->public_id, 'revision' => $revisionNo, 'source_hash' => bin2hex($locked->integrity_hash),
                    'currency' => 'VND', 'merchandise' => $locked->merchandise_amount, 'discount' => $locked->discount_amount,
                    'tax' => $locked->tax_amount, 'shipping' => $locked->shipping_amount, 'final' => $locked->final_amount,
                    'tier' => $locked->required_approval_tier, 'validity' => $validityDays, 'terms' => $commercialTerms,
                    'billing_address' => $locked->billing_address, 'shipping_address' => $locked->shipping_address,
                    'shipping_method' => $locked->shipping_method, 'shipping_preparation' => $locked->shipping_preparation,
                    'tax_calculation' => $locked->tax_calculation, 'payment_method' => $locked->payment_method,
                    'invoice_requested' => $locked->invoice_requested,
                ], JSON_THROW_ON_ERROR), true);
                $revision = QuoteRevision::query()->create([
                    'quote_id' => $quote->getKey(), 'revision_no' => $revisionNo, 'state' => 'draft', 'currency' => 'VND',
                    'merchandise_amount' => $locked->merchandise_amount, 'discount_amount' => $locked->discount_amount,
                    'tax_amount' => $locked->tax_amount, 'shipping_amount' => $locked->shipping_amount, 'final_amount' => $locked->final_amount,
                    'required_approval_tier' => $locked->required_approval_tier,
                    'pricing_configuration_revision' => $locked->pricing_configuration_revision,
                    'validity_configuration_revision' => config('quotation.validity_revision'),
                    'requested_validity_days' => $validityDays, 'commercial_terms' => $commercialTerms,
                    'billing_address' => $locked->billing_address, 'shipping_address' => $locked->shipping_address,
                    'shipping_method' => $locked->shipping_method, 'shipping_preparation' => $locked->shipping_preparation,
                    'tax_calculation' => $locked->tax_calculation, 'payment_method' => $locked->payment_method,
                    'invoice_requested' => $locked->invoice_requested,
                    'integrity_hash' => $integrity, 'proposer_user_account_id' => $actor->getKey(),
                ]);
                foreach ($locked->lines as $line) {
                    QuoteLine::query()->create([
                        'quote_revision_id' => $revision->getKey(), 'line_no' => $line->line_no, 'variant_id' => $line->variant_id,
                        'pricing_snapshot_id' => $line->pricing_snapshot_id, 'sku' => $line->sku, 'name' => $line->name,
                        'quantity' => $line->quantity, 'currency' => $line->currency, 'base_unit_amount' => $line->base_unit_amount,
                        'unit_amount' => $line->unit_amount, 'discount_amount' => $line->discount_amount,
                        'line_amount' => $line->line_amount, 'pricing_source' => $line->pricing_source,
                        'pricing_resolution' => $line->pricing_resolution,
                    ]);
                }
                $quote->forceFill(['current_revision_id' => $revision->getKey(), 'lock_version' => $quote->lock_version + 1])->save();
                DB::table('quote_operations')->insert([
                    'operation_key' => $operationKey, 'request_hash' => $hash, 'quote_revision_id' => $revision->getKey(),
                    'action' => 'revise', 'result_state' => 'draft', 'result_version' => 0, 'created_at' => now(),
                ]);

                return $revision->load('lines');
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existing($operationKey, $hash);
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existing(string $key, string $hash, bool $lock = false): ?QuoteRevision
    {
        $query = DB::table('quote_operations')->where('operation_key', $key);
        $operation = $lock ? $query->lockForUpdate()->first() : $query->first();
        if ($operation === null) {
            return null;
        }
        $data = get_object_vars($operation);
        if (! isset($data['request_hash'], $data['quote_revision_id']) || ! is_string($data['request_hash']) || ! is_int($data['quote_revision_id'])) {
            throw new DomainException('Stored quotation revision evidence is invalid.');
        }
        if (! hash_equals($data['request_hash'], $hash)) {
            throw new DomainException('Quotation revision identity was reused with different input.');
        }

        return QuoteRevision::query()->with('lines')->findOrFail($data['quote_revision_id']);
    }

    private function authorize(Quote $quote, UserAccount $actor): void
    {
        $scope = $quote->customer_id !== null ? AuthorizationScope::customer('quotes', $quote->customer_id) : AuthorizationScope::module('quotes');
        if (! $this->authorizer->allowsPersistent($actor, 'quotes.manage', $scope)) {
            throw new AuthorizationException('Quotation revision permission denied.');
        }
    }

    private function assertRevisable(Quote $quote, QuoteRevision $source): void
    {
        if ($quote->current_revision_id !== $source->getKey() || in_array($source->state, ['accepted', 'rejected', 'expired', 'converted'], true)) {
            throw new DomainException('Only the current non-terminal quotation revision may be revised.');
        }
    }

    private function assertSameOwner(Quote $quote, CreateQuotationCommand $command): void
    {
        if ($quote->customer_id !== $command->customerId || $quote->company_id !== $command->companyId) {
            throw new DomainException('Quotation revision cannot change Customer or Company ownership.');
        }
        $commandHash = $command->guestAccessToken === null
            ? null
            : hash_hmac('sha256', $command->guestAccessToken, (string) config('app.key'), true);
        $storedHash = $quote->guest_access_hash;
        if (($storedHash === null) !== ($commandHash === null)
            || ($commandHash !== null && ($storedHash === null || ! hash_equals($storedHash, $commandHash)))) {
            throw new AuthorizationException('Quotation revision guest identity is invalid.');
        }
    }
}
