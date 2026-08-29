<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\CMS\Application\Actions\CreateArticleDraft;
use App\Modules\CMS\Application\Actions\CreatePageDraft;
use App\Modules\CMS\Application\Actions\PublishArticle;
use App\Modules\CMS\Application\Actions\PublishPage;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TechnicalSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_indexable_and_transactional_pages_emit_explicit_canonical_and_robots(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
        $this->get(route('public.search', ['q' => 'pump', 'page' => 2]))->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('public.search').'">', false)
            ->assertDontSee('canonical" href="'.route('public.search', ['q' => 'pump']), false);
        $this->get(route('public.cart'))->assertOk()->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_robots_and_sitemap_use_only_public_cms_facts(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $page = app(CreatePageDraft::class)->execute($editor, 'seo-page', 'SEO Page', 'Body');
        $draft = app(CreatePageDraft::class)->execute($editor, 'draft-page', 'Draft Page', 'Body');
        $article = app(CreateArticleDraft::class)->execute($editor, 'seo-article', 'SEO Article', 'Body');
        app(PublishPage::class)->execute($publisher, $page['page'], 0);
        app(PublishArticle::class)->execute($publisher, $article['article'], 0);
        $category = Category::query()->create(['name' => 'SEO Category', 'slug' => 'seo-category', 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'SEO Product', 'slug' => 'seo-product', 'status' => 'active']);
        Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'SEO-001', 'name' => 'Default', 'status' => 'active']);
        $hiddenProduct = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Hidden Product', 'slug' => 'hidden-product', 'status' => 'inactive']);
        Variant::query()->create(['product_id' => $hiddenProduct->getKey(), 'sku' => 'SEO-002', 'name' => 'Default', 'status' => 'active']);

        $this->get(route('seo.robots'))->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin/')->assertSee('Sitemap: '.route('seo.sitemap'));
        $this->get(route('seo.sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('public.page', 'seo-page'))->assertSee(route('public.article', 'seo-article'))
            ->assertSee(route('public.category', 'seo-category'))->assertSee(route('public.product', 'seo-product'))
            ->assertDontSee(route('public.page', $draft['page']->slug))->assertDontSee(route('public.product', 'hidden-product'));
    }

    public function test_old_catalog_slugs_redirect_once_to_the_current_public_url(): void
    {
        $category = Category::query()->create(['name' => 'Pumps', 'slug' => 'pumps', 'status' => 'active']);
        $product = Product::query()->create([
            'primary_category_id' => $category->getKey(),
            'name' => 'Current pump',
            'slug' => 'current-pump',
            'status' => 'active',
        ]);
        Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'SEO-PUMP', 'name' => 'Default', 'status' => 'active']);

        $this->redirectFact('/san-pham/old-pump', '/san-pham/intermediate-pump', 'product', (int) $product->getKey());
        $this->redirectFact('/products/legacy-pump', '/products/intermediate-pump', 'product', (int) $product->getKey());

        $this->get('/san-pham/old-pump')->assertRedirect('/san-pham/current-pump')->assertStatus(301);
        $this->get('/products/legacy-pump')->assertRedirect('/san-pham/current-pump')->assertStatus(301);
        $this->get('/san-pham/current-pump')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('public.product', 'current-pump').'">', false);

        $product->forceFill(['status' => 'inactive'])->save();
        $this->get('/san-pham/old-pump')->assertNotFound();
    }

    public function test_catalog_pagination_has_stable_canonical_navigation_and_empty_page_noindex(): void
    {
        Category::query()->create(['name' => 'Pumps', 'slug' => 'paginated-pumps', 'status' => 'active']);

        $this->get(route('public.category', ['slug' => 'paginated-pumps', 'page' => 2]))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('public.category', ['slug' => 'paginated-pumps', 'page' => 2]).'">', false)
            ->assertSee('<link rel="prev" href="'.route('public.category', 'paginated-pumps').'">', false)
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_product_structured_data_matches_the_approved_fact_only_inventory(): void
    {
        $category = Category::query()->create(['name' => 'Industrial pumps', 'slug' => 'schema-pumps', 'status' => 'active']);
        $brand = Brand::query()->create(['name' => 'Kaiyo Safe', 'slug' => 'kaiyo-safe', 'status' => 'active']);
        $product = Product::query()->create([
            'primary_category_id' => $category->getKey(),
            'brand_id' => $brand->getKey(),
            'name' => 'Schema pump',
            'slug' => 'schema-pump',
            'description' => 'Verified facts </script><script>alert(1)</script>',
            'status' => 'active',
        ]);
        Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'SCHEMA-001', 'name' => 'Default', 'status' => 'active']);

        $html = $this->get(route('public.product', $product->slug))->assertOk()->getContent();
        self::assertIsString($html);
        self::assertSame(1, preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches));
        self::assertStringNotContainsString('</script><script>', $matches[1]);

        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($schema);
        self::assertSame(['@context', '@type', 'name', 'category', 'url', 'description', 'sku', 'brand'], array_keys($schema));
        self::assertSame('https://schema.org', $schema['@context']);
        self::assertSame('Product', $schema['@type']);
        self::assertSame('Schema pump', $schema['name']);
        self::assertSame('Industrial pumps', $schema['category']);
        self::assertSame(route('public.product', 'schema-pump'), $schema['url']);
        self::assertSame('SCHEMA-001', $schema['sku']);
        self::assertSame(['@type' => 'Brand', 'name' => 'Kaiyo Safe'], $schema['brand']);
        self::assertArrayNotHasKey('offers', $schema);
        self::assertArrayNotHasKey('aggregateRating', $schema);
        self::assertArrayNotHasKey('review', $schema);
        self::assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }

    private function redirectFact(string $source, string $target, string $ownerType, int $ownerId): void
    {
        DB::table('slug_redirects')->insert([
            'source_path' => $source,
            'target_path' => $target,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'status_code' => 301,
            'active' => true,
            'source_hash' => hash('sha256', $source, true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grant(UserAccount $account, string $code): void
    {
        $permission = PermissionDefinition::query()->where('code', $code)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module('content')->persistenceValues(), 'starts_at' => now()->subMinute(),
            'status' => 'active', 'granted_by_user_account_id' => $account->getKey(), 'reason' => 'SEO test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }
}
