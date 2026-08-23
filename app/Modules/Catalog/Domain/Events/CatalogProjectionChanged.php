<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

final readonly class CatalogProjectionChanged
{
    public function __construct(public string $aggregateType, public int $aggregateId, public int $version) {}
}
