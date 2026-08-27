<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Application\Actions\CreateBrand;
use App\Modules\Catalog\Application\Actions\CreateCategory;
use App\Modules\Catalog\Application\Actions\CreateProduct;
use App\Modules\Catalog\Application\Actions\DefineAttribute;
use App\Modules\Catalog\Application\Actions\SetAttributeValue;
use App\Modules\Catalog\Application\Actions\UpdateCategory;
use App\Modules\Catalog\Application\Actions\UpdateProduct;
use App\Modules\Catalog\Application\Queries\ListProducts;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_mutation_requires_server_permission(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CreateCategory::class)->execute(UserAccount::factory()->create(), 'Unauthorized');
    }

    public function test_category_hierarchy_rejects_cycles_and_uses_versions(): void
    {
        $actor = $this->actorWith('catalog.products.manage');
        $root = app(CreateCategory::class)->execute($actor, 'Industrial');
        $child = app(CreateCategory::class)->execute($actor, 'Pumps', parent: $root);

        try {
            app(UpdateCategory::class)->execute($actor, $root, 0, parent: $child);
            self::fail('A category cannot move below its descendant.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $updated = app(UpdateCategory::class)->execute($actor, $child, 0, name: 'Industrial Pumps');
        self::assertSame(1, $updated->lock_version);
        $this->expectException(DomainException::class);
        app(UpdateCategory::class)->execute($actor, $updated, 0, name: 'Stale');
    }

    public function test_product_creation_is_atomic_requires_variant_and_normalizes_reserved_sku_slug(): void
    {
        $actor = $this->actorWith('catalog.products.manage');
        $category = app(CreateCategory::class)->execute($actor, 'Tools');
        $brand = app(CreateBrand::class)->execute($actor, 'Kaiyo Pro');
        $create = app(CreateProduct::class);
        $product = $create->execute($actor, $category, 'Impact Driver', [['sku' => ' ky-imp.01 ', 'name' => 'Standard']], $brand);

        self::assertSame('impact-driver', $product->slug);
        self::assertSame('KY-IMP.01', $product->variants->firstOrFail()->sku);
        self::assertSame('draft', $product->status);

        try {
            $create->execute($actor, $category, 'Invalid Empty Product', []);
            self::fail('A Product requires a Variant.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $this->assertDatabaseMissing('products', ['slug' => 'invalid-empty-product']);

        $product->variants->firstOrFail()->delete();
        try {
            $create->execute($actor, $category, 'Duplicate SKU Product', [['sku' => 'KY-IMP.01', 'name' => 'Duplicate']]);
            self::fail('Soft-deleted SKU must remain reserved.');
        } catch (UniqueConstraintViolationException) {
            self::assertTrue(true);
        }
        $this->assertDatabaseMissing('products', ['slug' => 'duplicate-sku-product']);
    }

    public function test_product_activation_and_slug_changes_create_flat_redirect_facts(): void
    {
        $actor = $this->actorWith('catalog.products.manage');
        $category = app(CreateCategory::class)->execute($actor, 'Valves');
        $product = app(CreateProduct::class)->execute($actor, $category, 'Control Valve', [['sku' => 'VALVE-1', 'name' => 'DN25']]);
        $action = app(UpdateProduct::class);

        $active = $action->execute($actor, $product, 0, ['status' => 'active', 'slug' => 'control-valve-v2']);
        $latest = $action->execute($actor, $active, 1, ['slug' => 'control-valve-v3']);

        self::assertSame('active', $latest->status);
        $this->assertDatabaseHas('slug_redirects', ['source_path' => '/san-pham/control-valve', 'target_path' => '/san-pham/control-valve-v3', 'active' => true]);
        $this->assertDatabaseHas('slug_redirects', ['source_path' => '/san-pham/control-valve-v2', 'target_path' => '/san-pham/control-valve-v3', 'active' => true]);
        $this->assertDatabaseCount('slug_redirects', 2);
    }

    public function test_typed_attributes_have_one_owner_one_value_and_replace_deterministically(): void
    {
        $actor = $this->actorWith('catalog.products.manage');
        $category = app(CreateCategory::class)->execute($actor, 'Motors');
        $product = app(CreateProduct::class)->execute($actor, $category, 'Motor', [['sku' => 'MOTOR-1', 'name' => '1kW']]);
        $definition = app(DefineAttribute::class)->execute($actor, 'rated_power_kw', 'Rated power', 'decimal', true);
        $set = app(SetAttributeValue::class);
        $set->execute($actor, $definition, '1.2500', product: $product);
        $set->execute($actor, $definition, '1.5000', product: $product);

        $this->assertDatabaseCount('product_attribute_values', 1);
        $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->getKey(), 'decimal_value' => '1.5000']);
        self::assertSame(2, $product->refresh()->lock_version);
        self::assertSame(3, DB::table('catalog_change_events')->where('aggregate_type', 'product')->where('aggregate_id', $product->getKey())->count());
        self::assertSame(3, DB::table('dispatch_records')->where('aggregate_type', 'product')->where('aggregate_public_id', $product->public_id)->count());

        $this->expectException(DomainException::class);
        $set->execute($actor, $definition, 2, product: $product);
    }

    public function test_product_listing_is_eager_loaded_and_query_count_is_bounded(): void
    {
        $manager = $this->actorWith('catalog.products.manage');
        $reader = $this->actorWith('catalog.products.read');
        $category = app(CreateCategory::class)->execute($manager, 'Sensors');
        $brand = app(CreateBrand::class)->execute($manager, 'Sense');
        foreach (range(1, 5) as $number) {
            app(CreateProduct::class)->execute($manager, $category, 'Sensor '.$number, [['sku' => 'SENSOR-'.$number, 'name' => 'Default']], $brand);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $products = app(ListProducts::class)->execute($reader);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(5, $products);
        self::assertLessThanOrEqual(10, $queryCount);
        foreach ($products as $product) {
            self::assertTrue($product->relationLoaded('category'));
            self::assertTrue($product->relationLoaded('brand'));
            self::assertTrue($product->relationLoaded('variants'));
        }
    }

    private function actorWith(string $permissionCode): UserAccount
    {
        $actor = UserAccount::factory()->create([
            'two_factor_secret' => encrypt('catalog-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['catalog-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_at' => now(),
        ]);
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::global()->persistenceValues(),
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(),
            'reason' => 'Catalog test authority.',
            'identity_hash' => hash('sha256', $actor->getKey().'|'.$permission->getKey().'|'.random_bytes(8), true),
        ]);

        return $actor;
    }
}
