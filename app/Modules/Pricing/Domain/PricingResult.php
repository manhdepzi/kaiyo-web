<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain;

final readonly class PricingResult
{
    /** @param list<array{layer: string, source: string, amount: int}> $resolution */
    public function __construct(public string $currency, public string $quantity, public int $unitAmount, public int $lineAmount, public string $winningLayer, public string $sourceReference, public array $resolution, public string $rounding = 'HALF_UP') {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return get_object_vars($this);
    }
}
