<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class PublicSlugRedirector
{
    public function resolve(string $sourcePath): ?RedirectResponse
    {
        $redirect = DB::table('slug_redirects')
            ->where('source_hash', hash('sha256', $sourcePath, true))
            ->where('source_path', $sourcePath)
            ->where('active', true)
            ->first(['owner_type', 'owner_id', 'status_code']);

        if ($redirect === null) {
            return null;
        }

        $target = match ($redirect->owner_type) {
            'product' => $this->productTarget((int) $redirect->owner_id),
            'category' => $this->categoryTarget((int) $redirect->owner_id),
            'brand' => $this->brandTarget((int) $redirect->owner_id),
            default => null,
        };

        if ($target === null || $target === $sourcePath) {
            return null;
        }

        $status = in_array((int) $redirect->status_code, [301, 308], true)
            ? (int) $redirect->status_code
            : 301;

        return redirect($target, $status);
    }

    private function productTarget(int $id): ?string
    {
        $slug = Product::query()->whereKey($id)->where('status', 'active')->value('slug');

        return is_string($slug) ? route('public.product', ['slug' => $slug], false) : null;
    }

    private function categoryTarget(int $id): ?string
    {
        $slug = Category::query()->whereKey($id)->where('status', 'active')->value('slug');

        return is_string($slug) ? route('public.category', ['slug' => $slug], false) : null;
    }

    private function brandTarget(int $id): ?string
    {
        $slug = Brand::query()->whereKey($id)->where('status', 'active')->value('slug');

        return is_string($slug) ? route('public.brand', ['slug' => $slug], false) : null;
    }
}
