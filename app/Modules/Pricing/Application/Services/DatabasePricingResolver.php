<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Pricing\Domain\PriceCandidate;
use App\Modules\Pricing\Domain\PricingEngine;
use App\Modules\Pricing\Domain\PricingResult;
use App\Modules\Pricing\Domain\PromotionCalculator;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class DatabasePricingResolver
{
    public function __construct(private PricingEngine $engine, private PromotionCalculator $promotions) {}

    public function resolve(Variant $variant, string $quantity, ?int $customerId = null, ?int $companyId = null, ?int $salesTeamId = null): PricingResult
    {
        $configuration = PriceConfiguration::query()->where('status', 'active')->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->first();
        if ($configuration === null) {
            throw new DomainException('No active pricing configuration exists.');
        }
        $rules = DB::table('price_rules')->where('price_configuration_id', $configuration->getKey())
            ->where('variant_id', $variant->getKey())->where('minimum_quantity', '<=', $quantity)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->get();
        $candidates = [];
        foreach ($rules as $rule) {
            if (! $this->scopeEligible((string) $rule->scope_type, $rule->scope_id === null ? null : (int) $rule->scope_id, $customerId, $companyId, $salesTeamId)) {
                continue;
            }
            $candidates[] = new PriceCandidate((string) $rule->layer, (int) $rule->priority, (int) $rule->unit_amount, (string) $rule->source_reference);
        }
        $baseCandidates = array_values(array_filter($candidates, fn (PriceCandidate $candidate): bool => $candidate->layer === 'base'));
        $base = $this->engine->calculate($baseCandidates, '1')->unitAmount;

        $promotionRows = DB::table('promotions')->where('price_configuration_id', $configuration->getKey())->where('status', 'active')
            ->where('starts_at', '<=', now())->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->get();
        foreach ($promotionRows as $promotion) {
            if (! $this->promotionEligible((int) $promotion->id, $customerId, $companyId, $variant)) {
                continue;
            }
            if ($promotion->usage_limit !== null && DB::table('promotion_redemptions')->where('promotion_id', $promotion->id)->count() >= (int) $promotion->usage_limit) {
                continue;
            }
            if ($customerId !== null && $promotion->per_customer_limit !== null
                && DB::table('promotion_redemptions')->where('promotion_id', $promotion->id)->where('customer_id', $customerId)->count() >= (int) $promotion->per_customer_limit) {
                continue;
            }
            $source = 'promotion:'.(string) $promotion->public_id;
            $candidates[] = $promotion->type === 'fixed'
                ? $this->promotions->fixed($base, (int) $promotion->fixed_amount, (int) $promotion->priority, $source)
                : $this->promotions->percentage($base, $this->percentFromMicros((int) $promotion->percentage_micros), (int) $promotion->priority, $source);
        }

        return $this->engine->calculate($candidates, $quantity);
    }

    private function scopeEligible(string $type, ?int $id, ?int $customerId, ?int $companyId, ?int $salesTeamId): bool
    {
        return match ($type) {
            'global' => true,
            'customer' => $id !== null && $id === $customerId,
            'company' => $id !== null && $id === $companyId,
            'sales_team' => $id !== null && $id === $salesTeamId,
            default => false,
        };
    }

    private function promotionEligible(int $promotionId, ?int $customerId, ?int $companyId, Variant $variant): bool
    {
        $eligibilities = DB::table('promotion_eligibilities')->where('promotion_id', $promotionId)->get();
        if ($eligibilities->isEmpty()) {
            return true;
        }

        return $eligibilities->contains(fn (object $eligibility): bool => match ((string) $eligibility->dimension) {
            'global' => true,
            'variant' => (int) $eligibility->target_id === $variant->getKey(),
            'customer' => $customerId !== null && (int) $eligibility->target_id === $customerId,
            'company' => $companyId !== null && (int) $eligibility->target_id === $companyId,
            default => false,
        });
    }

    private function percentFromMicros(int $micros): string
    {
        $whole = intdiv($micros, 10000);
        $fraction = $micros % 10000;

        return $fraction === 0 ? (string) $whole : $whole.'.'.rtrim(str_pad((string) $fraction, 4, '0', STR_PAD_LEFT), '0');
    }
}
