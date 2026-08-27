<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SalesCustomerUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_customer_directory_requires_explicit_permission(): void
    {
        $account = UserAccount::factory()->create();

        $this->actingAs($account)->get(route('sales.customers'))->assertForbidden();
    }

    public function test_staff_permission_requires_confirmed_two_factor(): void
    {
        $account = UserAccount::factory()->create();
        $this->grant($account, 'crm.customers.read');

        $this->actingAs($account)->get(route('sales.customers'))
            ->assertRedirect(route('account.security'));
    }

    public function test_authorized_staff_receives_private_filtered_cursor_directory(): void
    {
        $account = UserAccount::factory()->create();
        $this->grant($account, 'crm.customers.read');
        $this->enableTwoFactor($account);

        foreach (range(1, 21) as $number) {
            Customer::query()->create([
                'display_name' => sprintf('Alpha Customer %02d', $number),
                'name_normalized' => sprintf('alpha customer %02d', $number),
                'primary_email_display' => sprintf('alpha%02d@example.test', $number),
                'primary_email_normalized' => sprintf('alpha%02d@example.test', $number),
                'status' => 'active',
            ]);
        }
        Customer::query()->create([
            'display_name' => 'Hidden Inactive',
            'name_normalized' => 'hidden inactive',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($account)->get(route('sales.customers', ['q' => 'alpha', 'status' => 'active']));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Alpha Customer')
            ->assertDontSee('Hidden Inactive')
            ->assertSee('Trang sau');
    }

    public function test_customer_360_honors_resource_scope_and_separate_commerce_entitlements(): void
    {
        $account = UserAccount::factory()->create();
        $allowed = Customer::query()->create(['display_name' => 'Allowed Customer', 'name_normalized' => 'allowed customer', 'status' => 'active']);
        $denied = Customer::query()->create(['display_name' => 'Denied Customer', 'name_normalized' => 'denied customer', 'status' => 'active']);
        $this->grant($account, 'crm.customers.read', AuthorizationScope::customer('crm', (int) $allowed->getKey()));
        $this->enableTwoFactor($account);

        $this->actingAs($account)->get(route('sales.customers.show', $allowed->public_id))
            ->assertOk()
            ->assertSee('Allowed Customer')
            ->assertSee('Không có quyền')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('sales.customers.show', $denied->public_id))->assertNotFound();
        $this->get(route('sales.customers'))->assertForbidden();
    }

    public function test_lead_directory_and_creation_keep_separate_permissions(): void
    {
        $reader = UserAccount::factory()->create();
        $this->grant($reader, 'crm.leads.read');
        $this->enableTwoFactor($reader);

        $this->actingAs($reader)->get(route('sales.leads'))
            ->assertOk()
            ->assertDontSee('Tạo Lead');
        $this->post(route('sales.leads.store'), [
            'display_name' => 'Forbidden Lead',
            'source' => 'web',
        ])->assertForbidden();

        $creator = UserAccount::factory()->create();
        $this->grant($creator, 'crm.leads.read');
        $this->grant($creator, 'crm.leads.create');
        $this->enableTwoFactor($creator);
        $this->actingAs($creator)->post(route('sales.leads.store'), [
            'display_name' => 'Qualified Prospect',
            'source' => 'trade-show',
            'company_name' => 'Prospect Company',
            'email' => 'prospect@example.test',
            'phone' => '+84901234567',
        ])->assertRedirect(route('sales.leads'));

        $lead = Lead::query()->sole();
        self::assertSame($creator->getKey(), $lead->owner_user_account_id);
        self::assertSame('new', $lead->status);
        $this->get(route('sales.leads', ['q' => 'qualified']))
            ->assertOk()
            ->assertSee('Qualified Prospect')
            ->assertSee('trade-show');
    }

    public function test_lead_detail_update_and_conversion_use_version_scope_and_idempotency(): void
    {
        $actor = UserAccount::factory()->create();
        foreach (['crm.leads.read', 'crm.leads.create', 'crm.leads.update', 'crm.leads.convert'] as $permission) {
            $this->grant($actor, $permission);
        }
        $this->enableTwoFactor($actor);
        $this->actingAs($actor)->post(route('sales.leads.store'), [
            'display_name' => 'Convertible Lead',
            'source' => 'campaign',
            'company_name' => 'Convertible Company',
            'email' => 'convertible@example.test',
        ])->assertRedirect(route('sales.leads'));
        $lead = Lead::query()->sole();

        $this->get(route('sales.leads.show', $lead->public_id))
            ->assertOk()
            ->assertSee('Chuyển đổi an toàn');
        $this->patch(route('sales.leads.update', $lead->public_id), [
            'expected_version' => 0,
            'status' => 'qualified',
            'source' => 'qualified-campaign',
        ])->assertRedirect(route('sales.leads.show', $lead->public_id));
        self::assertSame('qualified', $lead->refresh()->status);
        self::assertSame(1, $lead->lock_version);

        $payload = ['idempotency_key' => 'sales-ui-convert-001'];
        $this->post(route('sales.leads.convert', $lead->public_id), $payload)
            ->assertRedirect(route('sales.leads.show', $lead->public_id));
        $this->post(route('sales.leads.convert', $lead->public_id), $payload)
            ->assertRedirect(route('sales.leads.show', $lead->public_id));
        self::assertSame('converted', $lead->refresh()->status);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('companies', 1);

        $outsider = UserAccount::factory()->create();
        $this->grant($outsider, 'crm.leads.read', AuthorizationScope::owned('crm', (int) $outsider->getKey()));
        $this->enableTwoFactor($outsider);
        $this->actingAs($outsider)->get(route('sales.leads.show', $lead->public_id))->assertNotFound();
    }

    public function test_company_directory_and_creation_separate_read_create_and_verification(): void
    {
        $actor = UserAccount::factory()->create();
        $this->grant($actor, 'crm.companies.read');
        $this->grant($actor, 'crm.companies.create');
        $this->enableTwoFactor($actor);

        $this->actingAs($actor)->post(route('sales.companies.store'), [
            'legal_name' => 'Kaiyo Enterprise Company Limited',
            'display_name' => 'Kaiyo Enterprise',
            'tax_code' => 'VN-01020304',
            'source' => 'sales-ui',
        ])->assertRedirect(route('sales.companies'));
        $company = Company::query()->sole();
        self::assertSame('Kaiyo Enterprise', $company->display_name);
        $this->assertDatabaseMissing('crm_identity_keys', ['subject_type' => 'company', 'subject_id' => $company->getKey()]);
        $this->get(route('sales.companies', ['q' => 'kaiyo']))
            ->assertOk()->assertSee('Kaiyo Enterprise')->assertSee('VN-01020304');

        $reader = UserAccount::factory()->create();
        $this->grant($reader, 'crm.companies.read');
        $this->enableTwoFactor($reader);
        $this->actingAs($reader)->post(route('sales.companies.store'), [
            'legal_name' => 'Forbidden Company',
        ])->assertForbidden();
    }

    public function test_company_detail_membership_is_scoped_idempotent_and_grants_no_capability(): void
    {
        $manager = UserAccount::factory()->create();
        $this->grant($manager, 'crm.companies.read');
        $this->grant($manager, 'crm.companies.create');
        $this->grant($manager, 'crm.companies.manage_members');
        $this->enableTwoFactor($manager);
        $this->actingAs($manager)->post(route('sales.companies.store'), [
            'legal_name' => 'Membership Company',
        ])->assertRedirect(route('sales.companies'));
        $company = Company::query()->sole();
        $member = UserAccount::factory()->create();
        $payload = ['member_public_id' => $member->public_id];

        $this->post(route('sales.companies.members.store', $company->public_id), $payload)
            ->assertRedirect(route('sales.companies.show', $company->public_id));
        $this->post(route('sales.companies.members.store', $company->public_id), $payload)
            ->assertRedirect(route('sales.companies.show', $company->public_id));
        $this->assertDatabaseCount('company_memberships', 1);
        $this->assertDatabaseCount('company_member_capabilities', 0);
        $this->get(route('sales.companies.show', $company->public_id))
            ->assertOk()->assertSee($member->email_display)->assertSee('chưa có capability nào');

        $outsider = UserAccount::factory()->create();
        $other = Company::query()->create(['legal_name' => 'Other', 'display_name' => 'Other', 'name_normalized' => 'other', 'status' => 'active']);
        $this->grant($outsider, 'crm.companies.read', AuthorizationScope::company('crm', (int) $other->getKey()));
        $this->enableTwoFactor($outsider);
        $this->actingAs($outsider)->get(route('sales.companies.show', $company->public_id))->assertNotFound();
    }

    public function test_quote_and_order_directories_have_independent_entitlements(): void
    {
        $actor = UserAccount::factory()->create();
        $this->grant($actor, 'quotes.read', AuthorizationScope::module('quotes'));
        $this->enableTwoFactor($actor);

        $this->actingAs($actor)->get(route('sales.quotes'))
            ->assertOk()->assertSee('Báo giá')->assertSee('Chưa có Báo giá');
        $this->get(route('sales.orders'))->assertForbidden();

        $this->grant($actor, 'orders.read', AuthorizationScope::module('orders'));
        $this->get(route('sales.orders'))
            ->assertOk()->assertSee('Đơn hàng')->assertSee('Chưa có Đơn hàng');
    }

    private function grant(UserAccount $account, string $code, ?AuthorizationScope $scope = null): void
    {
        $permission = PermissionDefinition::query()->where('code', $code)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...($scope ?? AuthorizationScope::module('crm'))->persistenceValues(),
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'granted_by_user_account_id' => $account->getKey(),
            'reason' => 'Sales UI test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }

    private function enableTwoFactor(UserAccount $account): void
    {
        $account->forceFill([
            'two_factor_secret' => encrypt('sales-ui-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['sales-ui-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
}
