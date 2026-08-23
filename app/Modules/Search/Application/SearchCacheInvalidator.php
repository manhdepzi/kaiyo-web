<?php

declare(strict_types=1);

namespace App\Modules\Search\Application;

use Illuminate\Contracts\Cache\Repository;

final readonly class SearchCacheInvalidator
{
    private const VERSION_KEY = 'search:catalog-version';

    public function __construct(private Repository $cache) {}

    public function invalidate(): void
    {
        $this->cache->add(self::VERSION_KEY, 1);
        $this->cache->increment(self::VERSION_KEY);
    }
}
