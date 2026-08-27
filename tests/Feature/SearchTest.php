<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Foundation\Application\RelayDispatchRecords;
use App\Modules\Search\Application\SearchService;
use App\Modules\Search\Contracts\SearchAdapter;
use App\Modules\Search\Domain\SearchQuery;
use App\Modules\Search\Infrastructure\DatabaseSearchAdapter;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_contract_is_provider_neutral_and_exact_sku_ranks_first(): void
    {
        self::assertInstanceOf(DatabaseSearchAdapter::class, app(SearchAdapter::class));
        [$category] = $this->taxonomy();
        $this->product($category, null, 'ABC matching product', 'OTHER-1');
        $exact = $this->product($category, null, 'Different name', 'ABC');

        $result = app(SearchService::class)->search(new SearchQuery(' abc '));

        self::assertCount(2, $result->hits);
        self::assertSame($exact->sku, $result->hits[0]->sku);
        self::assertSame(0, $result->hits[0]->rank);
    }

    public function test_filters_and_public_sellability_are_enforced_with_stable_pagination(): void
    {
        [$categoryA, $brandA] = $this->taxonomy();
        [$categoryB, $brandB] = $this->taxonomy();
        $this->product($categoryA, $brandA, 'Industrial valve A', 'VALVE-A');
        $this->product($categoryB, $brandB, 'Industrial valve B', 'VALVE-B');
        $this->product($categoryA, $brandA, 'Hidden valve', 'VALVE-H', 'inactive');

        $result = app(SearchService::class)->search(new SearchQuery('valve', $categoryA->getKey(), $brandA->getKey(), 1, 1));

        self::assertCount(1, $result->hits);
        self::assertSame('VALVE-A', $result->hits[0]->sku);
        self::assertFalse($result->hasMore);
    }

    public function test_cache_is_derived_and_catalog_fact_invalidates_stale_results(): void
    {
        [$category] = $this->taxonomy();
        $first = $this->product($category, null, 'Control valve', 'CV-1');
        $service = app(SearchService::class);
        self::assertCount(1, $service->search(new SearchQuery('valve'))->hits);

        $this->product($category, null, 'Safety valve', 'SV-1');
        self::assertCount(1, $service->search(new SearchQuery('valve'))->hits);

        app(CatalogEventRecorder::class)->record('product', (int) $first->product_id, 1, 'catalog.updated');
        self::assertSame(1, DB::table('dispatch_records')->where('event_type', 'catalog.projection.changed')->count());
        app(RelayDispatchRecords::class)->execute(10);
        self::assertCount(2, $service->search(new SearchQuery('valve'))->hits);
    }

    public function test_wildcards_are_literal_and_query_count_is_bounded(): void
    {
        [$category] = $this->taxonomy();
        $this->product($category, null, 'Percent 100% valve', 'PCT-100');
        $this->product($category, null, 'Ordinary valve', 'ORD-1');
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $result = app(DatabaseSearchAdapter::class)->search(new SearchQuery('100%'));
        self::assertCount(1, $result->hits);
        self::assertSame('PCT-100', $result->hits[0]->sku);
        self::assertSame(1, $queries);
    }

    public function test_input_bounds_fail_closed(): void
    {
        $this->expectException(DomainException::class);
        new SearchQuery(str_repeat('x', 101), page: 101, perPage: 51);
    }

    /** @return array{Category, Brand} */
    private function taxonomy(): array
    {
        $suffix = random_int(1000, 9999);

        return [
            Category::query()->create(['name' => 'Category '.$suffix, 'slug' => 'category-'.$suffix, 'status' => 'active']),
            Brand::query()->create(['name' => 'Brand '.$suffix, 'slug' => 'brand-'.$suffix, 'status' => 'active']),
        ];
    }

    private function product(Category $category, ?Brand $brand, string $name, string $sku, string $status = 'active'): Variant
    {
        $suffix = random_int(10000, 99999);
        $product = Product::query()->create([
            'primary_category_id' => $category->getKey(), 'brand_id' => $brand?->getKey(), 'name' => $name,
            'slug' => 'product-'.$suffix, 'status' => $status,
        ]);

        return Variant::query()->create(['product_id' => $product->getKey(), 'sku' => $sku, 'name' => $name, 'status' => 'active']);
    }
}
