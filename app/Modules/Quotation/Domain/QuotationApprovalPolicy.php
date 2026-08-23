<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Domain;

use DomainException;

final class QuotationApprovalPolicy
{
    public function requiredTier(int $merchandiseAmount, int $discountAmount, int $finalAmount, ?bool $belowCost): string
    {
        if ($merchandiseAmount < 1 || $discountAmount < 0 || $discountAmount > $merchandiseAmount || $finalAmount < 0) {
            throw new DomainException('Quotation financial values are invalid.');
        }
        if ($discountAmount > 0 && $belowCost === null) {
            throw new DomainException('Negotiated pricing requires authoritative cost evidence.');
        }
        if ($this->exceedsBasisPoints($discountAmount, $merchandiseAmount, (int) config('quotation.maximum_discount_basis_points'))) {
            throw new DomainException('Quotation discount exceeds V1 authority.');
        }
        if ($belowCost === true || $this->exceedsBasisPoints($discountAmount, $merchandiseAmount, (int) config('quotation.finance_discount_basis_points')) || $finalAmount >= (int) config('quotation.finance_total_amount')) {
            return 'finance';
        }
        if ($this->exceedsBasisPoints($discountAmount, $merchandiseAmount, (int) config('quotation.manager_discount_basis_points')) || $finalAmount >= (int) config('quotation.manager_total_amount')) {
            return 'manager';
        }

        return 'sales';
    }

    public function permission(string $tier): string
    {
        return match ($tier) {
            'sales' => 'quotes.issue',
            'manager' => 'quotes.approve_manager',
            'finance' => 'quotes.approve_finance',
            default => throw new DomainException('Quotation approval tier is invalid.'),
        };
    }

    private function exceedsBasisPoints(int $discountAmount, int $merchandiseAmount, int $basisPoints): bool
    {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new DomainException('Quotation authority configuration is invalid.');
        }
        $thresholdAmount = intdiv($merchandiseAmount, 10_000) * $basisPoints
            + intdiv(($merchandiseAmount % 10_000) * $basisPoints, 10_000);

        return $discountAmount > $thresholdAmount;
    }
}
