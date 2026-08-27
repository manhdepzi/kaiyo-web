<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class DispatchFact
{
    /** @param array<string, bool|int|string|null> $payload */
    public function __construct(
        public string $identity,
        public string $type,
        public int $version,
        public string $aggregateType,
        public string $aggregatePublicId,
        public array $payload,
    ) {}
}
