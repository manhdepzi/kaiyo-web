<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Data;

final readonly class PaymentCancellationPreparation
{
    /** @param array<string, bool|int|string|null> $metadata */
    public function __construct(public string $action, public string $configurationRevision, public array $metadata = []) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return ['action' => $this->action, 'configuration_revision' => $this->configurationRevision, 'metadata' => $this->metadata];
    }
}
