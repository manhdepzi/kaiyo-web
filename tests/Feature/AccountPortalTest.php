<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Application\Queries\AccountPortalReader;
use App\Modules\CRM\Application\Services\CrmPartyService;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccountPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_account_can_provision_exactly_one_owned_customer_profile(): void
    {
        $account = UserAccount::factory()->create([
            'email_display' => 'Portal.Owner@example.test',
            'email_normalized' => 'portal.owner@example.test',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($account)->get('/account')
            ->assertOk()->assertSee('Hoàn tất hồ sơ khách hàng');
        $this->post('/account/profile', ['display_name' => 'Portal Owner'])
            ->assertRedirect('/account')->assertSessionHasNoErrors();
        $customer = Customer::query()->sole();
        self::assertSame($account->getKey(), $customer->user_account_id);
        self::assertSame('Portal Owner', $customer->display_name);
        $this->post('/account/profile', ['display_name' => 'Ignored Retry'])
            ->assertRedirect('/account')->assertSessionHasNoErrors();
        self::assertSame(1, Customer::query()->count());
        self::assertSame('Portal Owner', $customer->refresh()->display_name);
    }

    public function test_existing_verified_crm_identity_is_not_auto_linked(): void
    {
        $account = UserAccount::factory()->create([
            'email_display' => 'existing@example.test',
            'email_normalized' => 'existing@example.test',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        app(CrmPartyService::class)->createCustomer(
            'Existing CRM',
            'existing@example.test',
            null,
            'existing',
            ['email' => ['value' => 'existing@example.test', 'verified_at' => now()]],
        );

        $this->actingAs($account)->post('/account/profile', ['display_name' => 'Take Over'])
            ->assertRedirect()->assertSessionHasErrors('profile');
        self::assertNull(Customer::query()->sole()->user_account_id);
    }

    public function test_own_profile_update_is_versioned_and_cross_account_safe(): void
    {
        $owner = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $other = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer = Customer::query()->create([
            'user_account_id' => $owner->getKey(),
            'display_name' => 'Before',
            'name_normalized' => 'before',
            'status' => 'active',
        ]);

        $this->actingAs($owner)->patch('/account/profile', ['display_name' => 'After', 'expected_version' => 0])
            ->assertRedirect('/account')->assertSessionHasNoErrors();
        self::assertSame('After', $customer->refresh()->display_name);
        $this->patch('/account/profile', ['display_name' => 'Stale', 'expected_version' => 0])
            ->assertRedirect()->assertSessionHasErrors('profile');
        $this->actingAs($other)->patch('/account/profile', ['display_name' => 'Cross account', 'expected_version' => 1])
            ->assertRedirect()->assertSessionHasErrors('profile');
        self::assertSame('After', $customer->refresh()->display_name);
    }

    public function test_unverified_account_cannot_enter_portal(): void
    {
        $account = UserAccount::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $this->actingAs($account)->get('/account')->assertRedirect(route('verification.notice'));
    }

    public function test_company_membership_discloses_only_current_explicit_capabilities(): void
    {
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $grantor = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        Customer::query()->create([
            'user_account_id' => $account->getKey(), 'display_name' => 'Company Member',
            'name_normalized' => 'company member', 'status' => 'active',
        ]);
        $company = Company::query()->create([
            'legal_name' => 'Kaiyo Portal Company', 'display_name' => 'Kaiyo Portal Company',
            'name_normalized' => 'kaiyo portal company', 'status' => 'active',
        ]);
        $membershipId = DB::table('company_memberships')->insertGetId([
            'company_id' => $company->getKey(), 'user_account_id' => $account->getKey(), 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
            'identity_hash' => hash('sha256', 'portal-company-membership', true),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permission = PermissionDefinition::query()->where('code', 'orders.read')->firstOrFail();
        DB::table('company_member_capabilities')->insert([
            'company_membership_id' => $membershipId, 'permission_definition_id' => $permission->getKey(),
            'granted_by_user_account_id' => $grantor->getKey(),
            'identity_hash' => hash('sha256', 'portal-company-capability', true),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($account)->get(route('account'))->assertOk()
            ->assertSee('Kaiyo Portal Company')->assertSee('orders.read')
            ->assertDontSee('Membership chưa được cấp capability thương mại.');
        DB::table('company_member_capabilities')->where('company_membership_id', $membershipId)->update(['revoked_at' => now()]);
        $this->get(route('account'))->assertOk()->assertDontSee('orders.read')
            ->assertSee('Membership chưa được cấp capability thương mại.');
    }

    public function test_portal_projection_has_a_bounded_query_count_with_multiple_company_memberships(): void
    {
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $grantor = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        Customer::query()->create([
            'user_account_id' => $account->getKey(), 'display_name' => 'Bounded Portal',
            'name_normalized' => 'bounded portal', 'status' => 'active',
        ]);
        $permission = PermissionDefinition::query()->where('code', 'orders.read')->firstOrFail();
        foreach (range(1, 5) as $index) {
            $company = Company::query()->create([
                'legal_name' => "Portal Company {$index}", 'display_name' => "Portal Company {$index}",
                'name_normalized' => "portal company {$index}", 'status' => 'active',
            ]);
            $membershipId = DB::table('company_memberships')->insertGetId([
                'company_id' => $company->getKey(), 'user_account_id' => $account->getKey(), 'status' => 'active',
                'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
                'identity_hash' => hash('sha256', "bounded-membership-{$index}", true),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('company_member_capabilities')->insert([
                'company_membership_id' => $membershipId, 'permission_definition_id' => $permission->getKey(),
                'granted_by_user_account_id' => $grantor->getKey(),
                'identity_hash' => hash('sha256', "bounded-capability-{$index}", true),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });
        $portal = app(AccountPortalReader::class)->read($account);

        self::assertCount(5, $portal->companies);
        self::assertSame(10, $queryCount, 'Portal projection query count must not grow with memberships.');
    }

    public function test_portal_ssr_keeps_private_and_accessible_section_contracts(): void
    {
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        Customer::query()->create([
            'user_account_id' => $account->getKey(), 'display_name' => 'Accessible Portal',
            'name_normalized' => 'accessible portal', 'status' => 'active',
        ]);

        $this->actingAs($account)->get(route('account'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('href="#main-content"', false)
            ->assertSee('aria-labelledby="wishlist-heading"', false)
            ->assertSee('aria-labelledby="own-reviews-heading"', false)
            ->assertSee('aria-labelledby="notifications-heading"', false)
            ->assertSee('aria-labelledby="companies-heading"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="sms"', false);
    }

    public function test_order_detail_exposes_only_owned_commerce_state(): void
    {
        $owner = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $other = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer = Customer::query()->create(['user_account_id' => $owner->getKey(), 'display_name' => 'Owner', 'name_normalized' => 'owner', 'status' => 'active']);
        Customer::query()->create(['user_account_id' => $other->getKey(), 'display_name' => 'Other', 'name_normalized' => 'other', 'status' => 'active']);
        $cart = app(CartService::class)->forCustomer($customer);
        $order = Order::query()->create([
            'cart_id' => $cart->getKey(), 'customer_id' => $customer->getKey(), 'state' => 'pending', 'currency' => 'VND',
            'merchandise_amount' => 100_000, 'discount_amount' => 0, 'tax_amount' => 10_000, 'shipping_amount' => 20_000,
            'final_amount' => 130_000, 'payment_method' => 'cod', 'payment_preparation' => ['method' => 'cod'],
            'shipping_method' => 'standard', 'shipping_preparation' => ['method' => 'standard'],
            'tax_calculation' => ['revision' => 'test'], 'invoice_requested' => false, 'placed_at' => now(),
        ]);
        DB::table('order_status_history')->insert([
            'order_id' => $order->getKey(), 'from_state' => null, 'to_state' => 'pending',
            'reason_code' => 'checkout_placed', 'occurred_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'public_id' => (string) Str::ulid(),
            'customer_id' => $customer->getKey(),
            'order_id' => $order->getKey(),
            'channel' => 'in_app',
            'template_key' => 'order.confirmed',
            'business_fact_public_id' => (string) Str::ulid(),
            'idempotency_hash' => hash('sha256', 'account-notification-test', true),
            'attributes' => json_encode([
                'from_state' => 'pending',
                'order_public_id' => $order->public_id,
                'order_version' => 1,
                'to_state' => 'confirmed',
            ], JSON_THROW_ON_ERROR),
            'state' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('account.orders.show', $order->public_id))
            ->assertOk()->assertSee($order->public_id)->assertSee('130.000 ₫')->assertSee('pending')
            ->assertSee('Gửi yêu cầu hủy');
        $requestKey = (string) Str::ulid();
        $this->post(route('account.orders.cancellation.store', $order->public_id), [
            'request_key' => $requestKey,
            'reason' => 'Không còn nhu cầu nhận đơn hàng này.',
        ])->assertRedirect(route('account.orders.show', $order->public_id))->assertSessionHasNoErrors();
        $this->post(route('account.orders.cancellation.store', $order->public_id), [
            'request_key' => $requestKey,
            'reason' => 'Không còn nhu cầu nhận đơn hàng này.',
        ])->assertRedirect(route('account.orders.show', $order->public_id))->assertSessionHasNoErrors();
        self::assertSame(1, DB::table('cancellation_requests')->count());
        self::assertSame('requested', DB::table('cancellation_requests')->value('state'));
        $this->get(route('account.orders.show', $order->public_id))->assertOk()->assertSee('Đang chờ xử lý');
        $this->actingAs($owner)->get(route('account'))
            ->assertOk()->assertSee('Đơn hàng đã được xác nhận')->assertSee($order->public_id);
        $notificationPublicId = (string) DB::table('notifications')->value('public_id');
        $this->patch(route('account.notifications.read', $notificationPublicId))
            ->assertRedirect()->assertSessionHasNoErrors();
        self::assertNotNull(DB::table('notifications')->where('public_id', $notificationPublicId)->value('read_at'));
        $this->patch(route('account.notifications.read', $notificationPublicId))->assertRedirect();
        $this->actingAs($other)->get(route('account'))
            ->assertOk()->assertDontSee('Đơn hàng đã được xác nhận')->assertDontSee($order->public_id);
        $this->patch(route('account.notifications.read', $notificationPublicId))->assertNotFound();
        $this->actingAs($other)->get(route('account.orders.show', $order->public_id))->assertNotFound();
        $this->post(route('account.orders.cancellation.store', $order->public_id), [
            'request_key' => (string) Str::ulid(),
            'reason' => 'Cố gắng hủy đơn hàng của tài khoản khác.',
        ])->assertNotFound();
    }
}
