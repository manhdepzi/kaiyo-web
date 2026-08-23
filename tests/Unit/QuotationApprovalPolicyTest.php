<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Quotation\Domain\QuotationApprovalPolicy;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class QuotationApprovalPolicyTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, bool|null, string}> */
    public static function authorityBoundaries(): iterable
    {
        yield 'exactly 5 percent is Sales' => [1_000_000, 50_000, 950_000, false, 'sales'];
        yield 'one VND above 5 percent is Manager' => [1_000_000, 50_001, 949_999, false, 'manager'];
        yield 'exactly 15 percent is Manager' => [1_000_000, 150_000, 850_000, false, 'manager'];
        yield 'one VND above 15 percent is Finance' => [1_000_000, 150_001, 849_999, false, 'finance'];
        yield 'exactly 25 percent is Finance' => [1_000_000, 250_000, 750_000, false, 'finance'];
        yield 'exactly 100 million total is Manager' => [100_000_000, 0, 100_000_000, null, 'manager'];
        yield 'exactly 500 million total is Finance' => [500_000_000, 0, 500_000_000, null, 'finance'];
        yield 'below cost is Finance' => [1_000_000, 10_000, 990_000, true, 'finance'];
    }

    #[DataProvider('authorityBoundaries')]
    public function test_approved_d003_boundaries(int $merchandise, int $discount, int $final, ?bool $belowCost, string $expected): void
    {
        self::assertSame($expected, (new QuotationApprovalPolicy)->requiredTier($merchandise, $discount, $final, $belowCost));
    }

    public function test_above_25_percent_and_missing_cost_evidence_fail_closed(): void
    {
        $policy = new QuotationApprovalPolicy;
        try {
            $policy->requiredTier(1_000_000, 250_001, 749_999, false);
            self::fail('Discount above 25 percent must fail.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $this->expectException(DomainException::class);
        $policy->requiredTier(1_000_000, 10_000, 990_000, null);
    }
}
