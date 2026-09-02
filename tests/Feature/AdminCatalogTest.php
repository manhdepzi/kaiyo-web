<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_catalog_admin_requires_permission_and_manages_complete_public_product_detail(): void
    {
        $unauthorized = UserAccount::factory()->create();
        $this->actingAs($unauthorized)->get(route('admin.catalog'))->assertForbidden();

        $manager = UserAccount::factory()->create([
            'two_factor_secret' => encrypt('catalog-secret'), 'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(), 'two_factor_enabled_at' => now(),
        ]);
        $this->grant($manager, 'catalog.products.manage', 'catalog');
        $this->grant($manager, 'media.assets.manage', 'media');

        $this->actingAs($manager)->post(route('admin.catalog.categories.store'), [
            'name' => 'Van điều khiển', 'slug' => 'van-dieu-khien', 'sort_order' => 10,
        ])->assertRedirect(route('admin.catalog'));
        $category = Category::query()->where('slug', 'van-dieu-khien')->firstOrFail();

        $this->actingAs($manager)->post(route('admin.catalog.products.store'), [
            'name' => 'Van gió kiểm thử', 'slug' => 'van-gio-kiem-thu', 'category_public_id' => $category->public_id,
            'description' => 'Mô tả ngắn công khai.', 'detailed_description' => 'Nội dung kỹ thuật chi tiết do quản trị viên nhập.',
            'seo_title' => 'Van gió kiểm thử | Kaiyo', 'seo_description' => 'SEO description được quản trị có kiểm soát.',
            'variant_name' => 'Kích thước 300 × 300 mm', 'variant_sku' => 'TEST-VALVE-300', 'quantity_scale' => 0,
        ])->assertRedirect(route('admin.catalog'));
        $product = Product::query()->where('slug', 'van-gio-kiem-thu')->firstOrFail();
        self::assertSame('draft', $product->status);

        $this->actingAs($manager)->post(route('admin.catalog.specifications.store', $product->public_id), [
            'label' => 'Vật liệu', 'value' => 'Tôn mạ kẽm',
        ])->assertRedirect(route('admin.catalog'));

        $this->actingAs($manager)->post(route('admin.catalog.media.store', $product->public_id), [
            'file' => UploadedFile::fake()->image('van-gio.png', 1200, 900), 'purpose' => 'primary', 'sort_order' => 0,
        ])->assertRedirect(route('admin.catalog'));
        $video = new UploadedFile(public_path('videos/demo/product-placeholder.mp4'), 'video-mau.mp4', 'video/mp4', null, true);
        $this->actingAs($manager)->post(route('admin.catalog.media.store', $product->public_id), [
            'file' => $video, 'purpose' => 'video', 'sort_order' => 10,
        ])->assertRedirect(route('admin.catalog'));

        $product->refresh();
        $this->actingAs($manager)->patch(route('admin.catalog.products.update', $product->public_id), [
            'lock_version' => $product->lock_version, 'name' => $product->name, 'slug' => $product->slug,
            'category_public_id' => $category->public_id, 'status' => 'active', 'description' => $product->description,
            'detailed_description' => $product->detailed_description, 'seo_title' => $product->seo_title, 'seo_description' => $product->seo_description,
        ])->assertRedirect(route('admin.catalog'));

        $this->actingAs($manager)->get(route('admin.catalog'))->assertOk()
            ->assertSee('Van gió kiểm thử')->assertSee('video-mau.mp4')->assertSee('Vật liệu');
        $public = $this->get(route('public.product', $product->slug))->assertOk()
            ->assertSee('Nội dung kỹ thuật chi tiết do quản trị viên nhập.')
            ->assertSee('Tôn mạ kẽm')->assertSee('<video', false)
            ->assertSee('<title>Van gió kiểm thử | Kaiyo</title>', false);
        self::assertStringContainsString('/media/', $public->getContent());
        $this->assertDatabaseCount('catalog_media_references', 2);
    }

    private function grant(UserAccount $account, string $code, string $module): void
    {
        $permission = PermissionDefinition::query()->where('code', $code)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module($module)->persistenceValues(), 'starts_at' => now()->subMinute(), 'status' => 'active',
            'granted_by_user_account_id' => $account->getKey(), 'reason' => 'Admin Catalog test authority.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }
}
