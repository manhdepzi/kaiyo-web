<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Queries;

use App\Modules\Catalog\Application\Data\PublicProductImageView;
use App\Modules\Catalog\Application\Support\ProductPresentationCatalog;
use Illuminate\Support\Facades\DB;

final readonly class PublicProductContentReader
{
    public function __construct(private ProductPresentationCatalog $fallback) {}

    /** @return list<PublicProductImageView> */
    public function images(string $productPublicId, string $slug, string $productName): array
    {
        $assets = $this->assets($productPublicId, ['primary', 'gallery'], 'image/%');
        if ($assets === []) {
            return $this->fallback->imagesFor($slug, $productName);
        }

        return array_map(function (array $asset) use ($productName): PublicProductImageView {
            $size = DB::table('media_variants')->where('media_asset_id', $asset['id'])->where('variant_code', 'large')->first();

            return new PublicProductImageView(
                route('public.media', $asset['public_id']),
                $productName,
                $size === null ? 1200 : (int) $size->width,
                $size === null ? 1200 : (int) $size->height,
            );
        }, $assets);
    }

    /** @return array{url: string, mime: string, title: string}|null */
    public function video(string $productPublicId, string $slug): ?array
    {
        $asset = $this->assets($productPublicId, ['video'], 'video/%')[0] ?? null;
        if ($asset === null) {
            return $this->fallback->videoFor($slug);
        }

        return ['url' => route('public.media', $asset['public_id']), 'mime' => $asset['detected_mime'], 'title' => 'Video sản phẩm'];
    }

    /** @return array<string, string> */
    public function specifications(string $productPublicId, string $slug): array
    {
        $values = DB::table('product_attribute_values as values')
            ->join('products', 'products.id', '=', 'values.product_id')
            ->join('attribute_definitions as definitions', 'definitions.id', '=', 'values.attribute_definition_id')
            ->where('products.public_id', $productPublicId)
            ->where('definitions.status', 'active')
            ->where('definitions.code', 'like', 'spec.%')
            ->orderBy('definitions.name')
            ->get(['definitions.name', 'values.text_value', 'values.integer_value', 'values.decimal_value', 'values.boolean_value']);

        if ($values->isEmpty()) {
            return $this->fallback->specificationsFor($slug);
        }

        return $values->mapWithKeys(function (object $item): array {
            $value = $item->text_value ?? $item->integer_value ?? $item->decimal_value ?? ((bool) $item->boolean_value ? 'Có' : 'Không');

            return [(string) $item->name => (string) $value];
        })->all();
    }

    /** @return list<array{rating:int,title:string,body:string,customer:string,submitted_at:string}> */
    public function reviews(string $productPublicId): array
    {
        return array_values(DB::table('product_reviews as reviews')
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->join('customers', 'customers.id', '=', 'reviews.customer_id')
            ->where('products.public_id', $productPublicId)->where('reviews.status', 'approved')
            ->orderByDesc('reviews.submitted_at')->orderByDesc('reviews.id')->limit(50)
            ->get(['reviews.rating', 'reviews.title', 'reviews.body', 'reviews.submitted_at', 'customers.display_name'])
            ->map(fn (object $row): array => [
                'rating' => (int) $row->rating,
                'title' => (string) $row->title,
                'body' => (string) $row->body,
                'customer' => (string) $row->display_name,
                'submitted_at' => (string) $row->submitted_at,
            ])->all());
    }

    /**
     * @param  list<string>  $purposes
     * @return list<array{id: int, public_id: string, detected_mime: string}>
     */
    private function assets(string $productPublicId, array $purposes, string $mimePattern): array
    {
        $assets = DB::table('catalog_media_references as media_refs')
            ->join('products', 'products.id', '=', 'media_refs.product_id')
            ->join('media_assets as assets', 'assets.id', '=', 'media_refs.media_asset_id')
            ->where('products.public_id', $productPublicId)
            ->whereIn('media_refs.purpose', $purposes)
            ->where('assets.status', 'active')->where('assets.scan_status', 'clean')->where('assets.access_class', 'public')
            ->where('assets.detected_mime', 'like', $mimePattern)
            ->orderByRaw("CASE WHEN media_refs.purpose = 'primary' THEN 0 ELSE 1 END")
            ->orderBy('media_refs.sort_order')->orderBy('media_refs.id')
            ->get(['assets.id', 'assets.public_id', 'assets.detected_mime'])
            ->map(fn (object $asset): array => ['id' => (int) $asset->id, 'public_id' => (string) $asset->public_id, 'detected_mime' => (string) $asset->detected_mime])
            ->all();

        return array_values($assets);
    }
}
