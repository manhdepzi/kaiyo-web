<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

use DomainException;

final class PromotionCalculator
{
    public function fixed(int $baseAmount, int $discountAmount, int $priority, string $source, bool $eligible = true): PriceCandidate
    {
        if ($baseAmount < 0 || $discountAmount <= 0 || $discountAmount > $baseAmount) {
            throw new DomainException('Fixed promotion is invalid.');
        }

        return new PriceCandidate('promotion', $priority, $baseAmount - $discountAmount, $source, $eligible);
    }

    public function percentage(int $baseAmount, string $percent, int $priority, string $source, bool $eligible = true): PriceCandidate
    {
        if ($baseAmount < 0 || preg_match('/^([0-9]{1,3})(?:\.([0-9]{1,4}))?$/', $percent, $parts) !== 1) {
            throw new DomainException('Percentage promotion is invalid.');
        }
        $scaledPercent = ((int) $parts[1] * 10000) + (int) str_pad($parts[2] ?? '', 4, '0');
        if ($scaledPercent <= 0 || $scaledPercent > 1_000_000) {
            throw new DomainException('Percentage promotion must be greater than zero and at most 100.');
        }
        if ($baseAmount > intdiv(PHP_INT_MAX, $scaledPercent)) {
            throw new DomainException('Promotion arithmetic exceeds the supported range.');
        }
        $discount = intdiv(($baseAmount * $scaledPercent) + 500_000, 1_000_000);

        return new PriceCandidate('promotion', $priority, $baseAmount - $discount, $source, $eligible);
    }
}
