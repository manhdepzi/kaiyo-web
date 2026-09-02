<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\CustomerAddress;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerAddressBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_versioned_defaults_and_checkout_prefill(): void
    {
        [$owner, $customer] = $this->owner();

        $this->actingAs($owner)->post(route('account.addresses.store'), $this->payload([
            'label' => 'Nhà riêng',
            'recipient_name' => 'Nguyễn Văn Mạnh',
            'address_line_1' => '12 Nguyễn Trãi',
            'subdivision' => 'Hà Nội',
            'phone' => '0900000001',
        ]))->assertRedirect(route('account'))->assertSessionHasNoErrors();

        $home = CustomerAddress::query()->where('customer_id', $customer->getKey())->sole();
        self::assertTrue($home->is_default_shipping);
        self::assertTrue($home->is_default_billing);

        $this->post(route('account.addresses.store'), $this->payload([
            'label' => 'Văn phòng',
            'recipient_name' => 'Kaiyo Việt Nam',
            'address_line_1' => '88 Phạm Văn Đồng',
            'subdivision' => 'Hà Nội',
            'phone' => '0900000002',
            'is_default_shipping' => '1',
        ]))->assertRedirect(route('account'))->assertSessionHasNoErrors();

        $office = CustomerAddress::query()->where('label', 'Văn phòng')->sole();
        self::assertTrue($office->is_default_shipping);
        self::assertFalse($office->is_default_billing);
        self::assertFalse($home->refresh()->is_default_shipping);
        self::assertTrue($home->is_default_billing);
        self::assertGreaterThan(0, $home->lock_version);

        $this->get(route('account'))->assertOk()
            ->assertSee('Nhà riêng')->assertSee('Văn phòng')->assertSee('Giao hàng mặc định');

        $category = Category::query()->create(['name' => 'Ống gió', 'slug' => 'ong-gio-address-test', 'status' => 'active']);
        $product = Product::query()->create([
            'primary_category_id' => $category->getKey(),
            'name' => 'Ống gió thử địa chỉ',
            'slug' => 'ong-gio-thu-dia-chi',
            'status' => 'active',
        ]);
        $variant = Variant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'ADDRESS-PREFILL',
            'name' => 'Bản tiêu chuẩn',
            'quantity_scale' => 0,
            'status' => 'active',
        ]);
        $cart = app(CartService::class)->forCustomer($customer);
        app(CartService::class)->putLine($cart, $variant, '1', 'address-prefill-cart', 0);
        $this->get(route('public.checkout'))->assertOk()
            ->assertSee('Kaiyo Việt Nam')->assertSee('88 Phạm Văn Đồng')->assertSee('0900000002');

        $this->patch(route('account.addresses.update', $office->public_id), $this->payload([
            'expected_version' => $office->lock_version,
            'label' => 'Văn phòng Hà Nội',
            'recipient_name' => 'Kaiyo Việt Nam',
            'address_line_1' => '88 Phạm Văn Đồng',
            'subdivision' => 'Hà Nội',
            'is_default_shipping' => '1',
        ]))->assertRedirect(route('account'))->assertSessionHasNoErrors();
        self::assertSame('Văn phòng Hà Nội', $office->refresh()->label);

        $this->delete(route('account.addresses.destroy', $office->public_id), [
            'expected_version' => $office->lock_version,
        ])->assertRedirect(route('account'))->assertSessionHasNoErrors();

        self::assertSame('inactive', $office->refresh()->status);
        self::assertNotNull($office->deleted_at);
        self::assertTrue($home->refresh()->is_default_shipping);
        self::assertTrue($home->is_default_billing);
    }

    public function test_stale_and_cross_customer_address_mutations_are_rejected(): void
    {
        [$owner] = $this->owner();
        [$other] = $this->owner('other-address-owner@example.test');
        $this->actingAs($owner)->post(route('account.addresses.store'), $this->payload());
        $address = CustomerAddress::query()->sole();

        $this->patch(route('account.addresses.update', $address->public_id), $this->payload([
            'expected_version' => 0,
            'label' => 'Đã sửa',
        ]))->assertRedirect(route('account'))->assertSessionHasNoErrors();
        $this->patch(route('account.addresses.update', $address->public_id), $this->payload([
            'expected_version' => 0,
            'label' => 'Ghi đè cũ',
        ]))->assertRedirect(route('account'))->assertSessionHasErrors('address');

        $this->actingAs($other)->patch(route('account.addresses.update', $address->public_id), $this->payload([
            'expected_version' => 1,
            'label' => 'Chiếm quyền',
        ]))->assertNotFound();
        $this->delete(route('account.addresses.destroy', $address->public_id), ['expected_version' => 1])
            ->assertNotFound();
        self::assertSame('Đã sửa', $address->refresh()->label);
    }

    public function test_address_validation_and_server_side_limit_are_enforced(): void
    {
        [$owner, $customer] = $this->owner();
        $this->actingAs($owner)->post(route('account.addresses.store'), $this->payload([
            'country_code' => 'US',
        ]))->assertSessionHasErrors('country_code');

        for ($index = 1; $index <= 20; $index++) {
            CustomerAddress::query()->create([
                'public_id' => (string) Str::ulid(),
                'customer_id' => $customer->getKey(),
                ...$this->payload([
                    'label' => 'Địa chỉ '.$index,
                    'is_default_shipping' => $index === 1,
                    'is_default_billing' => $index === 1,
                ]),
                'status' => 'active',
            ]);
        }

        $this->post(route('account.addresses.store'), $this->payload([
            'label' => 'Địa chỉ vượt giới hạn',
        ]))->assertRedirect(route('account'))->assertSessionHasErrors('address');
        self::assertSame(20, CustomerAddress::query()->count());
    }

    /** @return array{UserAccount, Customer} */
    private function owner(string $email = 'address-owner@example.test'): array
    {
        $account = UserAccount::factory()->create([
            'email_display' => $email,
            'email_normalized' => $email,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $customer = Customer::query()->create([
            'user_account_id' => $account->getKey(),
            'display_name' => 'Address Owner',
            'name_normalized' => 'address owner '.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        return [$account, $customer];
    }

    /** @param array<string, bool|int|string> $overrides
     * @return array<string, bool|int|string>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'label' => 'Địa chỉ chính',
            'recipient_name' => 'Người nhận',
            'address_line_1' => '123 Đường thử nghiệm',
            'country_code' => 'VN',
        ], $overrides);
    }
}
