<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Data;

use DomainException;

final readonly class ShippingPreparation
{
    /** @param array<string, bool|int|string|null> $metadata */
    public function __construct(public string $method, public int $amount, public string $configurationRevision, public array $metadata = [])
    {
        if ($amount < 0) {
            throw new DomainException('Shipping amount cannot be negative.');
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return ['method' => $this->method, 'amount' => $this->amount, 'configuration_revision' => $this->configurationRevision, 'metadata' => $this->metadata];
    }
}
