<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerWishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_is_idempotent_customer_owned_and_visible_on_product_and_portal(): void
    {
        [$product] = $this->product();
        [$owner] = $this->owner();
        [$other] = $this->owner('wishlist-other@example.test');

        $this->post(route('account.wishlist.store', $product->public_id))->assertRedirect(route('login'));
        $this->actingAs($owner)->post(route('account.wishlist.store', $product->public_id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('account.wishlist.store', $product->public_id))
            ->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame(1, DB::table('customer_wishlist_items')->count());

        $this->get(route('public.product', $product->slug))->assertOk()->assertSee('Đã lưu · Bỏ yêu thích');
        $this->get(route('account'))->assertOk()->assertSee($product->name)->assertSee('Sản phẩm yêu thích');

        $this->actingAs($other)->delete(route('account.wishlist.destroy', $product->public_id))
            ->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame(1, DB::table('customer_wishlist_items')->count());
        $this->get(route('account'))->assertOk()->assertDontSee($product->name);

        $this->actingAs($owner)->delete(route('account.wishlist.destroy', $product->public_id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->delete(route('account.wishlist.destroy', $product->public_id))
            ->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame(0, DB::table('customer_wishlist_items')->count());
    }

    public function test_profile_active_product_and_server_side_limit_are_required(): void
    {
        [$product, $category] = $this->product();
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($account)->post(route('account.wishlist.store', $product->public_id))
            ->assertRedirect()->assertSessionHasErrors('wishlist');

        [, $customer] = $this->owner('wishlist-limit@example.test');
        $this->actingAs(UserAccount::query()->where('email_normalized', 'wishlist-limit@example.test')->sole());
        for ($index = 1; $index <= 100; $index++) {
            $saved = Product::query()->create([
                'primary_category_id' => $category->getKey(),
                'name' => 'Sản phẩm lưu '.$index,
                'slug' => 'san-pham-luu-'.$index,
                'status' => 'active',
            ]);
            DB::table('customer_wishlist_items')->insert([
                'customer_id' => $customer->getKey(),
                'product_id' => $saved->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->post(route('account.wishlist.store', $product->public_id))
            ->assertRedirect()->assertSessionHasErrors('wishlist');
        self::assertSame(100, DB::table('customer_wishlist_items')->where('customer_id', $customer->getKey())->count());

        $product->forceFill(['status' => 'inactive'])->save();
        $this->post(route('account.wishlist.store', $product->public_id))->assertNotFound();
    }

    /** @return array{Product, Category} */
    private function product(): array
    {
        $category = Category::query()->create(['name' => 'Van gió', 'slug' => 'van-gio-wishlist', 'status' => 'active']);
        $product = Product::query()->create([
            'primary_category_id' => $category->getKey(),
            'name' => 'Van gió lưu lượng',
            'slug' => 'van-gio-luu-luong-wishlist',
            'status' => 'active',
        ]);
        Variant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'WISHLIST-PRODUCT',
            'name' => 'Bản tiêu chuẩn',
            'quantity_scale' => 0,
            'status' => 'active',
        ]);

        return [$product, $category];
    }

    /** @return array{UserAccount, Customer} */
    private function owner(string $email = 'wishlist-owner@example.test'): array
    {
        $account = UserAccount::factory()->create([
            'email_display' => $email,
            'email_normalized' => $email,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $customer = Customer::query()->create([
            'user_account_id' => $account->getKey(),
            'display_name' => 'Wishlist Owner',
            'name_normalized' => 'wishlist owner '.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        return [$account, $customer];
    }
}
