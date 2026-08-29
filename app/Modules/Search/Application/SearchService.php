<?php

declare(strict_types=1);

namespace App\Modules\Search\Application;

use App\Modules\Search\Contracts\SearchAdapter;
use App\Modules\Search\Domain\SearchHit;
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
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            $result = $this->fromCache($cached);
            if ($result !== null) {
                return $result;
            }
        }

        $result = $this->adapter->search($query);
        $this->cache->put($key, $this->toCache($result), now()->addMinutes(5));

        return $result;
    }

    /**
     * Cache values intentionally contain scalars only because application
     * security disables PHP object unserialization for every shared store.
     *
     * @return array{hits: list<array{product_id: int, product_public_id: string, product_name: string, slug: string, variant_id: int, variant_public_id: string, variant_name: string, sku: string, rank: int}>, page: int, per_page: int, has_more: bool}
     */
    private function toCache(SearchResult $result): array
    {
        return [
            'hits' => array_map(fn (SearchHit $hit): array => [
                'product_id' => $hit->productId,
                'product_public_id' => $hit->productPublicId,
                'product_name' => $hit->productName,
                'slug' => $hit->slug,
                'variant_id' => $hit->variantId,
                'variant_public_id' => $hit->variantPublicId,
                'variant_name' => $hit->variantName,
                'sku' => $hit->sku,
                'rank' => $hit->rank,
            ], $result->hits),
            'page' => $result->page,
            'per_page' => $result->perPage,
            'has_more' => $result->hasMore,
        ];
    }

    /** @param array<mixed> $cached */
    private function fromCache(array $cached): ?SearchResult
    {
        if (! isset($cached['hits'], $cached['page'], $cached['per_page'], $cached['has_more'])
            || ! is_array($cached['hits']) || ! array_is_list($cached['hits'])
            || ! is_int($cached['page']) || ! is_int($cached['per_page']) || ! is_bool($cached['has_more'])) {
            return null;
        }

        $hits = [];
        foreach ($cached['hits'] as $row) {
            if (! is_array($row)
                || ! isset($row['product_id'], $row['product_public_id'], $row['product_name'], $row['slug'], $row['variant_id'], $row['variant_public_id'], $row['variant_name'], $row['sku'], $row['rank'])
                || ! is_int($row['product_id']) || ! is_string($row['product_public_id']) || ! is_string($row['product_name']) || ! is_string($row['slug'])
                || ! is_int($row['variant_id']) || ! is_string($row['variant_public_id']) || ! is_string($row['variant_name']) || ! is_string($row['sku']) || ! is_int($row['rank'])) {
                return null;
            }

            $hits[] = new SearchHit(
                $row['product_id'],
                $row['product_public_id'],
                $row['product_name'],
                $row['slug'],
                $row['variant_id'],
                $row['variant_public_id'],
                $row['variant_name'],
                $row['sku'],
                $row['rank'],
            );
        }

        return new SearchResult($hits, $cached['page'], $cached['per_page'], $cached['has_more']);
    }
}
