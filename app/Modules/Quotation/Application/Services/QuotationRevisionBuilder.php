<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Pricing\Application\Services\DatabasePricingResolver;
use App\Modules\Pricing\Application\Services\PricingSnapshotStore;
use App\Modules\Pricing\Domain\PriceCandidate;
use App\Modules\Pricing\Domain\PricingEngine;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Quotation\Application\Data\CreateQuotationCommand;
use App\Modules\Quotation\Domain\QuotationApprovalPolicy;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteLine;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class QuotationRevisionBuilder
{
    public function __construct(
        private DatabasePricingResolver $pricing,
        private PricingEngine $engine,
        private PricingSnapshotStore $snapshots,
        private TaxCalculationPort $tax,
        private ShippingPreparationPort $shipping,
        private QuotationApprovalPolicy $approval,
        private PermissionAuthorizer $authorizer,
    ) {}

    /**
     * Recalculates and persists one immutable draft revision. The caller must hold
     * the Quote row lock and update current_revision_id in the same transaction.
     */
    public function build(Quote $quote, CreateQuotationCommand $command, int $revisionNo): QuoteRevision
    {
        if ($revisionNo < 1) {
            throw new DomainException('Quotation revision number is invalid.');
        }
        $configuration = PriceConfiguration::query()->where('status', 'active')->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->lockForUpdate()->sole();
        $priced = [];
        $merchandise = 0;
        $discount = 0;
        $belowCost = false;
        foreach ($command->lines as $index => $input) {
            $variant = Variant::query()->whereKey($input['variant_id'])->where('status', 'active')->lockForUpdate()->firstOrFail();
            $base = $this->pricing->resolve($variant, $input['quantity'], $quote->customer_id, $quote->company_id);
            $price = $base;
            $negotiated = $input['negotiated_unit_amount'] ?? null;
            if ($negotiated !== null) {
                $this->authorizeNegotiation($command, $quote);
                $cost = $input['cost_unit_amount'] ?? null;
                if ($cost === null || $cost < 0) {
                    throw new DomainException('Negotiated pricing requires authoritative cost evidence.');
                }
                $belowCost = $belowCost || $negotiated < $cost;
                $price = $this->engine->calculate([
                    new PriceCandidate('base', 0, $base->unitAmount, $base->sourceReference),
                    new PriceCandidate('quotation', 0, $negotiated, 'quote:'.$quote->public_id, approved: true),
                ], $input['quantity']);
            }
            $snapshot = $this->snapshots->persist($configuration, $variant, $price);
            $baseLine = $this->lineAmount($base->unitAmount, $input['quantity']);
            $merchandise = $this->add($merchandise, $baseLine);
            $discount = $this->add($discount, $baseLine - $price->lineAmount);
            $priced[] = compact('variant', 'base', 'price', 'snapshot', 'index', 'baseLine');
        }
        $net = $merchandise - $discount;
        $tax = $this->tax->calculate(array_map(fn (array $line): array => [
            'variant_id' => $line['variant']->getKey(), 'quantity' => $line['price']->quantity, 'line_amount' => $line['price']->lineAmount,
        ], $priced), $command->billingAddress, $net, 'VND', $command->invoiceRequested);
        $shipping = $this->shipping->prepare($command->shippingMethod, $command->shippingAddress, $net, 'VND');
        $final = $this->add($this->add($net, $tax->amount), $shipping->amount);
        $tier = $this->approval->requiredTier($merchandise, $discount, $final, $discount > 0 ? $belowCost : null);
        if ($command->validityDays > (int) config('quotation.default_validity_days') && $tier === 'sales') {
            $tier = 'manager';
        }
        $integrity = hash('sha256', json_encode([
            'quote' => $quote->public_id, 'revision' => $revisionNo, 'currency' => 'VND', 'merchandise' => $merchandise,
            'discount' => $discount, 'tax' => $tax->snapshot(), 'shipping' => $shipping->snapshot(), 'final' => $final,
            'tier' => $tier, 'validity' => $command->validityDays, 'terms' => $command->commercialTerms,
            'billing_address' => $command->billingAddress->snapshot(), 'shipping_address' => $command->shippingAddress->snapshot(),
            'shipping_method' => $command->shippingMethod,
            'payment_method' => $command->paymentMethod, 'invoice_requested' => $command->invoiceRequested,
            'lines' => array_map(fn (array $line): array => [$line['variant']->getKey(), $line['price']->snapshot()], $priced),
        ], JSON_THROW_ON_ERROR), true);
        $revision = QuoteRevision::query()->create([
            'quote_id' => $quote->getKey(), 'revision_no' => $revisionNo, 'state' => 'draft', 'currency' => 'VND',
            'merchandise_amount' => $merchandise, 'discount_amount' => $discount, 'tax_amount' => $tax->amount,
            'shipping_amount' => $shipping->amount, 'final_amount' => $final, 'required_approval_tier' => $tier,
            'pricing_configuration_revision' => $configuration->public_id,
            'validity_configuration_revision' => config('quotation.validity_revision'),
            'requested_validity_days' => $command->validityDays, 'commercial_terms' => $command->commercialTerms,
            'billing_address' => $command->billingAddress->snapshot(), 'shipping_address' => $command->shippingAddress->snapshot(),
            'shipping_method' => $command->shippingMethod, 'shipping_preparation' => $shipping->snapshot(),
            'tax_calculation' => $tax->snapshot(), 'payment_method' => $command->paymentMethod,
            'invoice_requested' => $command->invoiceRequested,
            'integrity_hash' => $integrity, 'proposer_user_account_id' => $command->proposer?->getKey(),
        ]);
        foreach ($priced as $line) {
            QuoteLine::query()->create([
                'quote_revision_id' => $revision->getKey(), 'line_no' => $line['index'] + 1, 'variant_id' => $line['variant']->getKey(),
                'pricing_snapshot_id' => $line['snapshot']->getKey(), 'sku' => $line['variant']->sku, 'name' => $line['variant']->name,
                'quantity' => $line['price']->quantity, 'currency' => 'VND', 'base_unit_amount' => $line['base']->unitAmount,
                'unit_amount' => $line['price']->unitAmount, 'discount_amount' => $line['baseLine'] - $line['price']->lineAmount,
                'line_amount' => $line['price']->lineAmount, 'pricing_source' => $line['price']->sourceReference,
                'pricing_resolution' => $line['price']->resolution,
            ]);
        }

        return $revision->load('lines');
    }

    private function authorizeNegotiation(CreateQuotationCommand $command, Quote $quote): void
    {
        if ($command->proposer === null) {
            throw new AuthorizationException('Guest/Customer quotation requests cannot establish negotiated prices.');
        }
        $scope = $quote->customer_id !== null ? AuthorizationScope::customer('quotes', $quote->customer_id) : AuthorizationScope::module('quotes');
        if (! $this->authorizer->allowsPersistent($command->proposer, 'quotes.manage', $scope)) {
            throw new AuthorizationException('Negotiated quotation price permission denied.');
        }
    }

    private function lineAmount(int $unitAmount, string $quantity): int
    {
        return $this->engine->calculate([new PriceCandidate('base', 0, $unitAmount, 'quotation-baseline')], $quantity)->lineAmount;
    }

    private function add(int $left, int $right): int
    {
        if ($right < 0 || $left > PHP_INT_MAX - $right) {
            throw new DomainException('Quotation arithmetic exceeds the supported range.');
        }

        return $left + $right;
    }
}
