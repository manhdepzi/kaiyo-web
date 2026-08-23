<?php

declare(strict_types=1);

namespace App\Modules\Search\Contracts;

use App\Modules\Search\Domain\SearchQuery;
use App\Modules\Search\Domain\SearchResult;

interface SearchAdapter
{
    public function search(SearchQuery $query): SearchResult;
}
