<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoCatalogPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_is_idempotent_and_visible_with_logo_images_and_variants(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $this->seed(DemoCatalogSeeder::class);

        $this->assertDatabaseCount('products', 9);
        $this->assertDatabaseCount('variants', 27);

        $this->get('/')
            ->assertOk()
            ->assertSee('logo-kaiyo-wat-1024x560.webp', false)
            ->assertSee('Sản phẩm nổi bật')
            ->assertSee('Van chặn lửa');

        $this->get('/san-pham/ong-gio-tron-inox')
            ->assertOk()
            ->assertSee('ong-gio-tron-inox-1.jpg', false)
            ->assertSee('ong-gio-tron-inox-2.jpg', false)
            ->assertSee('Đường kính Ø100')
            ->assertSee('Đường kính Ø150')
            ->assertSee('Đường kính Ø200')
            ->assertSee('data-deep-zoom', false)
            ->assertSee('data-gallery-thumb="0"', false)
            ->assertSee('product-placeholder.mp4', false)
            ->assertSee('VIDEO MẪU')
            ->assertSee('data-variant-option', false)
            ->assertSee('data-quantity-increase', false)
            ->assertSee('Thuộc tính nổi bật')
            ->assertSee('BreadcrumbList', false)
            ->assertSee('max-image-preview:large', false)
            ->assertSee('Sản phẩm liên quan')
            ->assertDontSee('"price"', false);

        $this->get('/danh-muc/ong-gio-phu-kien')
            ->assertOk()
            ->assertSee('Ống gió bọc tấm MgO tiêu chuẩn EI')
            ->assertSee('Ống gió tròn tôn mạ kẽm')
            ->assertSee('Ống gió vuông bích TDC');

        $this->get('/danh-muc/mieng-gio-van-gio')
            ->assertOk()
            ->assertSee('Van chặn lửa động cơ')
            ->assertSee('Van chặn lửa FD tiêu chuẩn EI90');

        $this->get('/san-pham/van-chan-lua-fd-tieu-chuan-ei-90')
            ->assertOk()
            ->assertSee('product-placeholder.mp4', false)
            ->assertSee('data-gallery-thumb="3"', false);
    }
}
