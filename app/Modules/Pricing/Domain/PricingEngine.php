<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

use DomainException;

final class PricingEngine
{
    /** @param list<PriceCandidate> $candidates */
    public function calculate(array $candidates, string $quantity, string $currency = 'VND'): PricingResult
    {
        if ($currency !== 'VND') {
            throw new DomainException('V1 Pricing supports VND only.');
        }
        $quantityUnits = $this->quantityUnits($quantity);
        $resolution = [];
        $winner = null;
        foreach (PriceCandidate::LAYERS as $layer) {
            $eligible = array_values(array_filter($candidates, fn (PriceCandidate $candidate): bool => $candidate->layer === $layer && $candidate->eligible));
            if ($eligible === []) {
                continue;
            }
            usort($eligible, fn (PriceCandidate $left, PriceCandidate $right): int => $right->priority <=> $left->priority);
            if (isset($eligible[1]) && $eligible[0]->priority === $eligible[1]->priority) {
                throw new DomainException('Pricing configuration has ambiguous winning priority.');
            }
            $winner = $eligible[0];
            $resolution[] = ['layer' => $layer, 'source' => $winner->sourceReference, 'amount' => $winner->unitAmount];
        }
        if ($winner === null || ! collect($candidates)->contains(fn (PriceCandidate $candidate): bool => $candidate->layer === 'base' && $candidate->eligible)) {
            throw new DomainException('An eligible base price is required.');
        }
        if ($winner->unitAmount > intdiv(PHP_INT_MAX, $quantityUnits)) {
            throw new DomainException('Pricing arithmetic exceeds the supported range.');
        }
        $numerator = $winner->unitAmount * $quantityUnits;
        $lineAmount = intdiv($numerator + 5000, 10000);

        return new PricingResult($currency, $quantity, $winner->unitAmount, $lineAmount, $winner->layer, $winner->sourceReference, $resolution);
    }

    private function quantityUnits(string $quantity): int
    {
        if (preg_match('/^([0-9]{1,14})(?:\.([0-9]{1,4}))?$/', $quantity, $parts) !== 1) {
            throw new DomainException('Quantity must be a positive decimal with at most four places.');
        }
        $units = ((int) $parts[1] * 10000) + (int) str_pad($parts[2] ?? '', 4, '0');
        if ($units < 1) {
            throw new DomainException('Quantity must be positive.');
        }

        return $units;
    }
}
