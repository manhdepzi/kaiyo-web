<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Data;

final readonly class PaymentPreparation
{
    /** @param array<string, bool|int|string|null> $metadata */
    public function __construct(public string $method, public string $configurationRevision, public array $metadata = []) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return ['method' => $this->method, 'configuration_revision' => $this->configurationRevision, 'metadata' => $this->metadata];
    }
}
