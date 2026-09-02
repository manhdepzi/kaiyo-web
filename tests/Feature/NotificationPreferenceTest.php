<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_preferences_are_owned_versioned_and_provider_neutral(): void
    {
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer = Customer::query()->create([
            'user_account_id' => $account->getKey(), 'display_name' => 'Preference Owner',
            'name_normalized' => 'preference owner', 'status' => 'active',
        ]);

        $this->actingAs($account)->get(route('account'))->assertOk()
            ->assertSee('Tùy chọn thông báo đơn hàng')->assertSee('Thông báo giao dịch bắt buộc');
        $this->patch(route('account.notification-preferences.update'), [
            'expected_version' => 0, 'email' => 1,
        ])->assertRedirect(route('account'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notification_preferences', [
            'customer_id' => $customer->getKey(), 'order_updates_email' => 1,
            'order_updates_sms' => 0, 'lock_version' => 1,
        ]);
        $this->patch(route('account.notification-preferences.update'), [
            'expected_version' => 0,
        ])->assertSessionHasErrors('preferences');
        $this->patch(route('account.notification-preferences.update'), [
            'expected_version' => 1, 'email' => 1, 'sms' => 1,
        ])->assertSessionHasErrors('preferences');

        $customer->forceFill(['primary_phone_display' => '0901234567', 'primary_phone_e164' => '+84901234567'])->save();
        $this->patch(route('account.notification-preferences.update'), [
            'expected_version' => 1, 'email' => 1, 'sms' => 1,
        ])->assertRedirect(route('account'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notification_preferences', [
            'customer_id' => $customer->getKey(), 'order_updates_email' => 1,
            'order_updates_sms' => 1, 'lock_version' => 2,
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_missing_customer_profile_cannot_create_preferences(): void
    {
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($account)->patch(route('account.notification-preferences.update'), [
            'expected_version' => 0, 'email' => 1,
        ])->assertSessionHasErrors('preferences');
        $this->assertDatabaseCount('notification_preferences', 0);
    }
}
