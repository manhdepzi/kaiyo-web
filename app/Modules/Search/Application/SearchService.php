<?php

declare(strict_types=1);

namespace App\Modules\Search\Application;

use App\Modules\Search\Contracts\SearchAdapter;
use App\Modules\Search\Domain\SearchQuery;
use App\Modules\Search\Domain\SearchResult;
use Illuminate\Contracts\Cache\Repository;

final readonly class SearchService
{
    private const VERSION_KEY = 'search:catalog-version';

    public function __construct(private SearchAdapter $adapter, private Repository $cache) {}

    public function search(SearchQuery $query): SearchResult
    {
        $version = (int) $this->cache->get(self::VERSION_KEY, 1);
        $key = 'search:v'.$version.':'.hash('sha256', json_encode($query->normalized(), JSON_THROW_ON_ERROR));

        return $this->cache->remember($key, now()->addMinutes(5), fn (): SearchResult => $this->adapter->search($query));
    }
}
