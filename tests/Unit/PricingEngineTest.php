<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Pricing\Domain\PriceCandidate;
use App\Modules\Pricing\Domain\PricingEngine;
use App\Modules\Pricing\Domain\PromotionCalculator;
use DomainException;
use PHPUnit\Framework\TestCase;

final class PricingEngineTest extends TestCase
{
    public function test_specificity_replaces_each_earlier_layer_without_stacking(): void
    {
        $result = (new PricingEngine)->calculate([
            new PriceCandidate('base', 1, 100_000, 'base-r1'),
            new PriceCandidate('promotion', 10, 90_000, 'promo-r1'),
            new PriceCandidate('b2b', 5, 85_000, 'tier-r2'),
            new PriceCandidate('override', 9, 80_000, 'company-r3'),
            new PriceCandidate('quotation', 1, 78_000, 'quote-r4'),
        ], '2.5000');

        self::assertSame(78_000, $result->unitAmount);
        self::assertSame(195_000, $result->lineAmount);
        self::assertSame('quotation', $result->winningLayer);
        self::assertCount(5, $result->resolution);
    }

    public function test_one_priority_winner_per_layer_and_ineligible_rules_do_not_apply(): void
    {
        $result = (new PricingEngine)->calculate([
            new PriceCandidate('base', 1, 100, 'base'),
            new PriceCandidate('promotion', 10, 70, 'not-eligible', false),
            new PriceCandidate('promotion', 9, 80, 'winner'),
            new PriceCandidate('promotion', 8, 75, 'lower-priority'),
        ], '1');

        self::assertSame(80, $result->unitAmount);
        self::assertSame('winner', $result->sourceReference);
    }

    public function test_equal_top_priority_fails_closed(): void
    {
        $this->expectException(DomainException::class);
        (new PricingEngine)->calculate([
            new PriceCandidate('base', 1, 100, 'base-a'),
            new PriceCandidate('base', 1, 99, 'base-b'),
        ], '1');
    }

    public function test_vnd_line_rounding_is_half_up_at_line_level(): void
    {
        $engine = new PricingEngine;
        self::assertSame(1, $engine->calculate([new PriceCandidate('base', 1, 1, 'base')], '0.5000')->lineAmount);
        self::assertSame(0, $engine->calculate([new PriceCandidate('base', 1, 1, 'base')], '0.4999')->lineAmount);
    }

    public function test_percentage_and_fixed_promotions_use_integer_half_up_and_replacement(): void
    {
        $promotions = new PromotionCalculator;
        $percent = $promotions->percentage(105, '10', 1, 'ten-percent');
        $fixed = $promotions->fixed(105, 20, 2, 'fixed');

        self::assertSame(94, $percent->unitAmount);
        self::assertSame(85, $fixed->unitAmount);
        self::assertSame(85, (new PricingEngine)->calculate([new PriceCandidate('base', 1, 105, 'base'), $percent, $fixed], '1')->unitAmount);
    }

    public function test_unapproved_manual_or_quote_price_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        new PriceCandidate('quotation', 1, 90, 'quote', approved: false);
    }

    public function test_missing_base_invalid_currency_or_quantity_fails(): void
    {
        $engine = new PricingEngine;
        foreach ([
            fn () => $engine->calculate([new PriceCandidate('b2b', 1, 90, 'tier')], '1'),
            fn () => $engine->calculate([new PriceCandidate('base', 1, 100, 'base')], '1', 'USD'),
            fn () => $engine->calculate([new PriceCandidate('base', 1, 100, 'base')], '0'),
        ] as $case) {
            try {
                $case();
                self::fail('Invalid calculation must fail.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }
}
