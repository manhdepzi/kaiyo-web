<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Actions\CreateCategory;
use App\Modules\Catalog\Application\Actions\CreateProduct;
use App\Modules\Catalog\Application\Actions\CreateVariant;
use App\Modules\Catalog\Application\Actions\SetProductSpecification;
use App\Modules\Catalog\Application\Actions\UpdateCategory;
use App\Modules\Catalog\Application\Actions\UpdateProduct;
use App\Modules\Catalog\Application\Actions\UpdateVariant;
use App\Modules\Catalog\Application\Queries\AdminCatalogReader;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Media\Application\MediaService;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

final class AdminCatalogController
{
    public function index(Request $request, AdminCatalogReader $reader): View
    {
        return view('admin.catalog', $reader->read($this->actor($request)));
    }

    public function storeCategory(Request $request, CreateCategory $action): RedirectResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'parent_public_id' => ['nullable', 'string', 'size:26', 'exists:categories,public_id'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        try {
            $action->execute($this->actor($request), (string) $values['name'], $this->optional($values['slug'] ?? null), $this->category($values['parent_public_id'] ?? null), (int) $values['sort_order']);

            return to_route('admin.catalog')->with('status', 'Đã tạo danh mục mới.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'category');
        }
    }

    public function updateCategory(Request $request, string $category, UpdateCategory $action): RedirectResponse
    {
        $model = $this->requiredCategory($category);
        $values = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($model->getKey())],
            'parent_public_id' => ['nullable', 'string', 'size:26', 'exists:categories,public_id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        try {
            $parent = $this->category($values['parent_public_id'] ?? null);
            $action->execute($this->actor($request), $model, (int) $values['lock_version'], (string) $values['name'], (string) $values['slug'], $parent, $parent === null, (string) $values['status']);

            return to_route('admin.catalog')->with('status', 'Đã cập nhật danh mục.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'category');
        }
    }

    public function storeProduct(Request $request, CreateProduct $create, UpdateProduct $update): RedirectResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:240'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'category_public_id' => ['required', 'string', 'size:26', 'exists:categories,public_id'],
            'brand_public_id' => ['nullable', 'string', 'size:26', 'exists:brands,public_id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'detailed_description' => ['nullable', 'string', 'max:50000'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:180'],
            'variant_name' => ['required', 'string', 'max:200'],
            'variant_sku' => ['required', 'string', 'max:100', 'unique:variants,sku'],
            'quantity_scale' => ['required', 'integer', 'between:0,4'],
        ]);

        try {
            $actor = $this->actor($request);
            $product = $create->execute(
                $actor,
                $this->requiredCategory((string) $values['category_public_id']),
                (string) $values['name'],
                [['sku' => (string) $values['variant_sku'], 'name' => (string) $values['variant_name'], 'quantity_scale' => (int) $values['quantity_scale']]],
                $this->brand($values['brand_public_id'] ?? null),
                $this->optional($values['slug'] ?? null),
                $this->optional($values['description'] ?? null),
            );
            $update->execute($actor, $product, 0, [
                'detailed_description' => $this->optional($values['detailed_description'] ?? null),
                'seo_title' => $this->optional($values['seo_title'] ?? null),
                'seo_description' => $this->optional($values['seo_description'] ?? null),
            ]);

            return to_route('admin.catalog')->with('status', 'Đã tạo sản phẩm nháp và biến thể đầu tiên.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'product');
        }
    }

    public function updateProduct(Request $request, string $product, UpdateProduct $action): RedirectResponse
    {
        $model = $this->product($product);
        $values = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:240'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($model->getKey())],
            'category_public_id' => ['required', 'string', 'size:26', 'exists:categories,public_id'],
            'brand_public_id' => ['nullable', 'string', 'size:26', 'exists:brands,public_id'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'detailed_description' => ['nullable', 'string', 'max:50000'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:180'],
        ]);

        try {
            $category = $this->requiredCategory((string) $values['category_public_id']);
            $brand = $this->brand($values['brand_public_id'] ?? null);
            $action->execute($this->actor($request), $model, (int) $values['lock_version'], [
                'name' => (string) $values['name'], 'slug' => (string) $values['slug'], 'status' => (string) $values['status'],
                'primary_category_id' => (int) $category->getKey(), 'brand_id' => $brand?->getKey(),
                'description' => $this->optional($values['description'] ?? null),
                'detailed_description' => $this->optional($values['detailed_description'] ?? null),
                'seo_title' => $this->optional($values['seo_title'] ?? null),
                'seo_description' => $this->optional($values['seo_description'] ?? null),
            ]);

            return to_route('admin.catalog')->with('status', 'Đã cập nhật chi tiết sản phẩm.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'product');
        }
    }

    public function storeVariant(Request $request, string $product, CreateVariant $action): RedirectResponse
    {
        $values = $request->validate(['sku' => ['required', 'string', 'max:100', 'unique:variants,sku'], 'name' => ['required', 'string', 'max:200'], 'quantity_scale' => ['required', 'integer', 'between:0,4']]);
        try {
            $action->execute($this->actor($request), $this->product($product), (string) $values['sku'], (string) $values['name'], (int) $values['quantity_scale']);

            return to_route('admin.catalog')->with('status', 'Đã thêm biến thể sản phẩm.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'variant');
        }
    }

    public function updateVariant(Request $request, string $variant, UpdateVariant $action): RedirectResponse
    {
        $model = Variant::query()->where('public_id', $variant)->firstOrFail();
        $values = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'], 'sku' => ['required', 'string', 'max:100', Rule::unique('variants', 'sku')->ignore($model->getKey())],
            'name' => ['required', 'string', 'max:200'], 'quantity_scale' => ['required', 'integer', 'between:0,4'], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        try {
            $action->execute($this->actor($request), $model, (int) $values['lock_version'], (string) $values['sku'], (string) $values['name'], (int) $values['quantity_scale'], (string) $values['status']);

            return to_route('admin.catalog')->with('status', 'Đã cập nhật biến thể.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'variant');
        }
    }

    public function storeSpecification(Request $request, string $product, SetProductSpecification $action): RedirectResponse
    {
        $values = $request->validate(['label' => ['required', 'string', 'max:160'], 'value' => ['required', 'string', 'max:191']]);
        try {
            $action->execute($this->actor($request), $this->product($product), (string) $values['label'], (string) $values['value']);

            return to_route('admin.catalog')->with('status', 'Đã lưu thông số kỹ thuật.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'specification');
        }
    }

    public function uploadMedia(Request $request, string $product, MediaService $media): RedirectResponse
    {
        $values = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf'],
            'purpose' => ['required', Rule::in(['primary', 'gallery', 'video', 'document'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        try {
            $model = $this->product($product);
            $asset = $media->upload($this->actor($request), $values['file'], 'public');
            $media->attachToCatalog($this->actor($request), $asset, (int) $model->getKey(), null, (string) $values['purpose'], (int) $values['sort_order']);

            return to_route('admin.catalog')->with('status', 'Đã tải lên và gắn media vào sản phẩm.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'media');
        }
    }

    public function detachMedia(Request $request, string $product, string $asset, string $purpose, MediaService $media): RedirectResponse
    {
        try {
            $media->detachFromCatalog($this->actor($request), MediaAsset::query()->where('public_id', $asset)->firstOrFail(), (int) $this->product($product)->getKey(), $purpose);

            return to_route('admin.catalog')->with('status', 'Đã gỡ media khỏi sản phẩm.');
        } catch (Throwable $exception) {
            return $this->failure($exception, 'media');
        }
    }

    private function actor(Request $request): UserAccount
    {
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 403);

        return $actor;
    }

    private function category(mixed $publicId): ?Category
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return Category::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function requiredCategory(string $publicId): Category
    {
        return Category::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function brand(mixed $publicId): ?Brand
    {
        return is_string($publicId) && $publicId !== '' ? Brand::query()->where('public_id', $publicId)->firstOrFail() : null;
    }

    private function product(string $publicId): Product
    {
        return Product::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function failure(Throwable $exception, string $key): RedirectResponse
    {
        report($exception);
        $message = $exception instanceof DomainException ? $exception->getMessage() : 'Không thể hoàn tất thao tác. Vui lòng kiểm tra dữ liệu và thử lại.';

        return back()->withInput()->withErrors([$key => $message]);
    }
}
