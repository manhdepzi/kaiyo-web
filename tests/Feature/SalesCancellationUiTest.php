<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Order\Application\Actions\ManageOrderCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SalesCancellationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_review_and_deny_customer_cancellation_request(): void
    {
        $owner = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer = Customer::query()->create([
            'user_account_id' => $owner->getKey(), 'display_name' => 'Cancellation Customer',
            'name_normalized' => 'cancellation customer', 'status' => 'active',
        ]);
        $cart = app(CartService::class)->forCustomer($customer);
        $order = Order::query()->create([
            'cart_id' => $cart->getKey(), 'customer_id' => $customer->getKey(), 'state' => 'pending', 'currency' => 'VND',
            'merchandise_amount' => 100000, 'discount_amount' => 0, 'tax_amount' => 0, 'shipping_amount' => 0,
            'final_amount' => 100000, 'payment_method' => 'cod', 'payment_preparation' => ['method' => 'cod'],
            'shipping_method' => 'standard', 'shipping_preparation' => ['method' => 'standard'],
            'tax_calculation' => ['revision' => 'test'], 'invoice_requested' => false, 'placed_at' => now(),
        ]);
        $cancellation = app(ManageOrderCancellation::class)->request(
            $order,
            $owner,
            'Khách hàng thay đổi nhu cầu trước khi xử lý.',
            'customer-cancel-ui-test',
        );

        $outsider = $this->staffAccount();
        $this->actingAs($outsider)->get(route('sales.cancellations'))->assertForbidden();

        $decider = $this->staffAccount();
        $this->grant($decider, 'orders.cancel_decide');
        $this->actingAs($decider)->get(route('sales.cancellations'))
            ->assertOk()->assertSee($order->public_id)->assertSee('Cancellation Customer')
            ->assertSee('Khách hàng thay đổi nhu cầu trước khi xử lý.');
        $this->patch(route('sales.cancellations.decide', $cancellation->public_id), [
            'decision' => 'deny', 'expected_version' => 0, 'decision_key' => (string) Str::ulid(),
            'reason' => 'Đơn đã được xác nhận giữ nguyên theo trao đổi với khách hàng.',
        ])->assertRedirect(route('sales.cancellations'))->assertSessionHasNoErrors();

        self::assertSame('denied', $cancellation->refresh()->state);
        self::assertSame('pending', $order->refresh()->state);
        $this->get(route('sales.cancellations'))->assertOk()->assertDontSee($order->public_id);
    }

    private function staffAccount(): UserAccount
    {
        return UserAccount::factory()->create([
            'status' => 'active', 'email_verified_at' => now(),
            'two_factor_secret' => encrypt('cancellation-ui-secret'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(), 'two_factor_enabled_at' => now(),
        ]);
    }

    private function grant(UserAccount $account, string $code): void
    {
        $permission = PermissionDefinition::query()->where('code', $code)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module('orders')->persistenceValues(), 'starts_at' => now()->subMinute(),
            'status' => 'active', 'granted_by_user_account_id' => $account->getKey(),
            'reason' => 'Sales cancellation UI test.', 'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }
}
