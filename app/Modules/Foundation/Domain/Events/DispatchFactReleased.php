<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Domain\Events;

final readonly class DispatchFactReleased
{
    /** @param array<string, bool|int|string|null> $payload */
    public function __construct(
        public string $recordPublicId,
        public string $type,
        public int $version,
        public string $aggregateType,
        public string $aggregatePublicId,
        public array $payload,
    ) {}
}
