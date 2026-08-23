<?php

declare(strict_types=1);

namespace App\Modules\Search\Infrastructure;

use App\Modules\Search\Contracts\SearchAdapter;
use App\Modules\Search\Domain\SearchHit;
use App\Modules\Search\Domain\SearchQuery;
use App\Modules\Search\Domain\SearchResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DatabaseSearchAdapter implements SearchAdapter
{
    public function search(SearchQuery $query): SearchResult
    {
        $like = '%'.$this->escapeLike($query->term).'%';
        $prefix = $this->escapeLike($query->term).'%';
        $exactSku = mb_strtoupper($query->term, 'UTF-8');
        $builder = DB::table('variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('categories as c', 'c.id', '=', 'p.primary_category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('p.status', 'active')->whereNull('p.deleted_at')
            ->where('v.status', 'active')->whereNull('v.deleted_at')
            ->where('c.status', 'active')->whereNull('c.deleted_at')
            ->where(fn (Builder $q) => $q->whereNull('p.brand_id')->orWhere(fn (Builder $brand) => $brand->where('b.status', 'active')->whereNull('b.deleted_at')))
            ->when($query->categoryId !== null, fn (Builder $q) => $q->where('p.primary_category_id', $query->categoryId))
            ->when($query->brandId !== null, fn (Builder $q) => $q->where('p.brand_id', $query->brandId));

        if ($query->term !== '') {
            $builder->where(function (Builder $q) use ($exactSku, $like): void {
                $q->where('v.sku', $exactSku)
                    ->orWhereRaw("LOWER(v.sku) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(v.name) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(p.name) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(p.slug) LIKE ? ESCAPE '!'", [$like]);
            });
        }

        $rankSql = $query->term === ''
            ? '4'
            : "CASE WHEN v.sku = ? THEN 0 WHEN LOWER(v.sku) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(p.name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END";
        $rankBindings = $query->term === '' ? [] : [$exactSku, $prefix, $prefix];
        $rows = $builder
            ->select(['p.id as product_id', 'p.public_id as product_public_id', 'p.name as product_name', 'p.slug', 'v.id as variant_id', 'v.public_id as variant_public_id', 'v.name as variant_name', 'v.sku'])
            ->selectRaw($rankSql.' as search_rank', $rankBindings)
            ->orderBy('search_rank')->orderBy('p.name')->orderBy('v.sku')->orderBy('v.id')
            ->offset(($query->page - 1) * $query->perPage)->limit($query->perPage + 1)->get();

        $hasMore = $rows->count() > $query->perPage;
        $hits = array_values($rows->take($query->perPage)->map(fn (object $row) => new SearchHit(
            (int) $row->product_id, (string) $row->product_public_id, (string) $row->product_name, (string) $row->slug,
            (int) $row->variant_id, (string) $row->variant_public_id, (string) $row->variant_name, (string) $row->sku, (int) $row->search_rank,
        ))->all());

        return new SearchResult($hits, $query->page, $query->perPage, $hasMore);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
