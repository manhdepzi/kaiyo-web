<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\CRM\Application\Actions\AssignOwnership;
use App\Modules\CRM\Application\Actions\ConvertLead;
use App\Modules\CRM\Application\Actions\CreateCompany;
use App\Modules\CRM\Application\Actions\CreateContact;
use App\Modules\CRM\Application\Actions\CreateCustomer;
use App\Modules\CRM\Application\Actions\CreateLead;
use App\Modules\CRM\Application\Actions\ManageCompanyMembership;
use App\Modules\CRM\Application\Actions\UpdateCustomer;
use App\Modules\CRM\Application\Services\CompanyCapabilityAuthorizer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrmCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_exact_identity_is_normalized_private_unique_and_transactional(): void
    {
        $actor = $this->actorWith('crm.customers.create');
        $create = app(CreateCustomer::class);
        $customer = $create->execute($actor, 'Nguyễn Văn A', 'Person@Example.COM', '+84 912 345 678', 'web', now(), now());

        self::assertSame('person@example.com', $customer->primary_email_normalized);
        self::assertSame('+84912345678', $customer->primary_phone_e164);
        $this->assertDatabaseMissing('crm_identity_keys', ['normalized_hash' => 'person@example.com']);
        $this->assertDatabaseCount('crm_identity_keys', 2);

        try {
            $create->execute($actor, 'Another Person', 'person@example.com', null, 'manual', now());
            self::fail('A verified exact identity must not be owned twice.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('crm_identity_keys', 2);
    }

    public function test_fuzzy_name_opens_review_but_does_not_merge_or_reject(): void
    {
        $actor = $this->actorWith('crm.customers.create');
        $create = app(CreateCustomer::class);
        $first = $create->execute($actor, 'Acme Industrial Buyer');
        $second = $create->execute($actor, 'Acme Industrial Buyers');

        self::assertNotSame($first->getKey(), $second->getKey());
        self::assertSame('duplicate_review', $second->status);
        $this->assertDatabaseHas('duplicate_reviews', [
            'candidate_customer_id' => $second->getKey(),
            'target_customer_id' => $first->getKey(),
            'match_kind' => 'fuzzy_name',
            'status' => 'open',
        ]);
    }

    public function test_update_is_exact_scope_allowlisted_and_optimistically_versioned(): void
    {
        $creator = $this->actorWith('crm.customers.create');
        $customer = app(CreateCustomer::class)->execute($creator, 'Scoped Customer');
        $editor = UserAccount::factory()->create();
        $this->grant($editor, 'crm.customers.update', AuthorizationScope::customer('crm', (int) $customer->getKey()));

        $updated = app(UpdateCustomer::class)->execute($editor, $customer, 0, ['display_name' => 'Scoped Customer Updated']);
        self::assertSame(1, $updated->lock_version);
        self::assertSame('scoped customer updated', $updated->name_normalized);

        $this->expectException(DomainException::class);
        app(UpdateCustomer::class)->execute($editor, $updated, 0, ['display_name' => 'Stale']);
    }

    public function test_cross_scope_and_invalid_contact_ownership_fail_closed(): void
    {
        $creator = $this->actorWith('crm.customers.create');
        $first = app(CreateCustomer::class)->execute($creator, 'First Customer');
        $second = app(CreateCustomer::class)->execute($creator, 'Second Customer');
        $editor = UserAccount::factory()->create();
        $this->grant($editor, 'crm.customers.update', AuthorizationScope::customer('crm', (int) $first->getKey()));

        try {
            app(UpdateCustomer::class)->execute($editor, $second, 0, ['display_name' => 'Forbidden']);
            self::fail('Cross-customer update must fail.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $contactManager = $this->actorWith('crm.contacts.manage');
        $this->expectException(DomainException::class);
        app(CreateContact::class)->execute($contactManager, 'Invalid Contact', null, null);
    }

    public function test_membership_alone_grants_nothing_and_explicit_capability_is_required(): void
    {
        $manager = $this->actorWith('crm.companies.create', 'crm.companies.manage_members', 'orders.read');
        $company = app(CreateCompany::class)->execute($manager, 'Kaiyo Buyer Co.');
        $member = UserAccount::factory()->create();
        $capabilities = app(CompanyCapabilityAuthorizer::class);

        app(ManageCompanyMembership::class)->add($manager, $company, $member);
        self::assertFalse($capabilities->allows($member, $company, 'orders.read'));

        app(ManageCompanyMembership::class)->add($manager, $company, $member, ['orders.read']);
        self::assertTrue($capabilities->allows($member, $company, 'orders.read'));
        self::assertFalse($capabilities->allows($member, $company, 'orders.manage'));
        $this->assertDatabaseHas('authorization_events', [
            'event_type' => 'company_capability_granted',
            'target_type' => 'company',
            'target_public_id' => $company->public_id,
            'actor_user_account_id' => $manager->getKey(),
            'subject_user_account_id' => $member->getKey(),
        ]);
        self::assertTrue(app(ManageCompanyMembership::class)->revokeCapability($manager, $company, $member, 'orders.read'));
        self::assertFalse(app(ManageCompanyMembership::class)->revokeCapability($manager, $company, $member, 'orders.read'));
        self::assertFalse($capabilities->allows($member, $company, 'orders.read'));
        self::assertSame(1, \DB::table('authorization_events')->where('event_type', 'company_capability_revoked')->count());
    }

    public function test_company_capability_delegation_rejects_unheld_and_high_impact_authority(): void
    {
        $manager = $this->actorWith('crm.companies.create', 'crm.companies.manage_members');
        $company = app(CreateCompany::class)->execute($manager, 'Delegation Boundary Co.');
        $member = UserAccount::factory()->create();
        $action = app(ManageCompanyMembership::class);
        $action->add($manager, $company, $member);

        try {
            $action->add($manager, $company, $member, ['orders.read']);
            self::fail('An actor must not delegate authority they do not hold persistently.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $this->assertDatabaseCount('company_member_capabilities', 0);

        $this->grant($manager, 'orders.manage', AuthorizationScope::company('orders', (int) $company->getKey()));
        try {
            $action->add($manager, $company, $member, ['orders.manage']);
            self::fail('High-impact authority must use the governed dual-control workflow.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $this->assertDatabaseCount('company_member_capabilities', 0);
    }

    public function test_ownership_reassignment_closes_history_and_keeps_one_active_owner(): void
    {
        $actor = $this->actorWith('crm.customers.create', 'crm.customers.update');
        $customer = app(CreateCustomer::class)->execute($actor, 'Owned Customer');
        $firstOwner = UserAccount::factory()->create();
        $secondOwner = UserAccount::factory()->create();

        app(AssignOwnership::class)->execute($actor, $firstOwner, 'Initial routing.', customer: $customer);
        app(AssignOwnership::class)->execute($actor, $secondOwner, 'Territory changed.', customer: $customer);

        self::assertSame(1, \DB::table('ownership_assignments')->where('customer_id', $customer->getKey())->whereNull('ends_at')->count());
        $this->assertDatabaseHas('ownership_assignments', ['customer_id' => $customer->getKey(), 'owner_user_account_id' => $secondOwner->getKey(), 'ends_at' => null]);
        self::assertNotNull(\DB::table('ownership_assignments')->where('owner_user_account_id', $firstOwner->getKey())->value('ends_at'));
    }

    public function test_lead_conversion_reuses_verified_exact_party_and_retry_returns_same_result(): void
    {
        $actor = $this->actorWith('crm.customers.create', 'crm.leads.create', 'crm.leads.convert');
        $customer = app(CreateCustomer::class)->execute($actor, 'Confirmed Customer', 'confirmed@example.com', null, 'existing', now());
        $lead = app(CreateLead::class)->execute($actor, 'campaign', 'Confirmed Customer', null, 'CONFIRMED@example.com');

        $first = app(ConvertLead::class)->execute($actor, $lead, 'conversion-001', emailVerified: true);
        $retry = app(ConvertLead::class)->execute($actor, $lead->refresh(), 'conversion-001', emailVerified: true);

        self::assertTrue($customer->is($first->customer));
        self::assertTrue($first->customer?->is($retry->customer) ?? false);
        self::assertSame('converted', $retry->lead->status);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_lead_conversion_creates_one_authoritative_party_and_rejects_conflicting_retry(): void
    {
        $actor = $this->actorWith('crm.leads.create', 'crm.leads.convert');
        $lead = app(CreateLead::class)->execute($actor, 'event', 'New Buyer', 'New Buyer LLC', 'new@example.com', '+12025550123', 'US-123');

        $result = app(ConvertLead::class)->execute($actor, $lead, 'conversion-002', true, true, true);
        self::assertNotNull($result->customer);
        self::assertNotNull($result->company);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('companies', 1);

        $this->expectException(DomainException::class);
        app(ConvertLead::class)->execute($actor, $lead->refresh(), 'different-command', true, true, true);
    }

    private function actorWith(string ...$permissions): UserAccount
    {
        $actor = UserAccount::factory()->create([
            'two_factor_secret' => encrypt('crm-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['crm-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_at' => now(),
        ]);
        foreach ($permissions as $permission) {
            $this->grant($actor, $permission, AuthorizationScope::global());
        }

        return $actor;
    }

    private function grant(UserAccount $account, string $permissionCode, AuthorizationScope $scope): ScopedGrant
    {
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();

        return ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...$scope->persistenceValues(),
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'granted_by_user_account_id' => $account->getKey(),
            'reason' => 'CRM test authority.',
            'identity_hash' => hash('sha256', $account->getKey().'|'.$permission->getKey().'|'.random_bytes(8), true),
        ]);
    }
}
