<?php

declare(strict_types=1);

namespace App\Modules\Search\Domain;

final readonly class SearchResult
{
    /** @param list<SearchHit> $hits */
    public function __construct(public array $hits, public int $page, public int $perPage, public bool $hasMore) {}
}
