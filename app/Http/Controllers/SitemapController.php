<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class SitemapController
{
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('public.about'), 'lastmod' => null],
            ['loc' => route('public.contact'), 'lastmod' => null],
            ['loc' => route('public.faq'), 'lastmod' => null],
        ];
        $urls = [...$urls, ...$this->catalogUrls('categories', 'public.category'), ...$this->catalogUrls('brands', 'public.brand'), ...$this->productUrls()];
        $pages = DB::table('pages')->where('status', 'published')->whereNull('deleted_at')->whereNotNull('published_revision_id')->orderBy('id')->get(['slug', 'updated_at']);
        foreach ($pages as $page) {
            $values = get_object_vars($page);
            $urls[] = ['loc' => route('public.page', (string) $values['slug']), 'lastmod' => $this->date($values['updated_at'])];
        }
        $articles = DB::table('articles')->where('status', 'published')->whereNull('deleted_at')->whereNotNull('published_revision_id')->orderBy('id')->get(['slug', 'updated_at']);
        foreach ($articles as $article) {
            $values = get_object_vars($article);
            $urls[] = ['loc' => route('public.article', (string) $values['slug']), 'lastmod' => $this->date($values['updated_at'])];
        }

        return response()->view('seo.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** @return list<array{loc:string,lastmod:?string}> */
    private function catalogUrls(string $table, string $routeName): array
    {
        $rows = DB::table($table)->where('status', 'active')->whereNull('deleted_at')->orderBy('id')->get(['slug', 'updated_at']);

        return array_values($rows->map(function (object $row) use ($routeName): array {
            $values = get_object_vars($row);

            return ['loc' => route($routeName, (string) $values['slug']), 'lastmod' => $this->date($values['updated_at'])];
        })->all());
    }

    /** @return list<array{loc:string,lastmod:?string}> */
    private function productUrls(): array
    {
        $rows = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.primary_category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('products.status', 'active')
            ->whereNull('products.deleted_at')
            ->where('categories.status', 'active')
            ->whereNull('categories.deleted_at')
            ->where(fn ($query) => $query->whereNull('products.brand_id')->orWhere(function ($brand): void {
                $brand->where('brands.status', 'active')->whereNull('brands.deleted_at');
            }))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('variants')
                ->whereColumn('variants.product_id', 'products.id')
                ->where('variants.status', 'active')
                ->whereNull('variants.deleted_at'))
            ->orderBy('products.id')
            ->get(['products.slug', 'products.updated_at']);

        return array_values($rows->map(function (object $row): array {
            $values = get_object_vars($row);

            return ['loc' => route('public.product', (string) $values['slug']), 'lastmod' => $this->date($values['updated_at'])];
        })->all());
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }
}
