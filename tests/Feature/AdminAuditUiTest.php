<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminAuditUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_ui_requires_high_permission_two_factor_and_hides_snapshot_hashes(): void
    {
        $outsider = UserAccount::factory()->create();
        $this->actingAs($outsider)->get(route('admin.audit'))->assertForbidden();

        $admin = UserAccount::factory()->create();
        $permission = PermissionDefinition::query()->where('code', 'system.audit.read')->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $admin->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module('system')->persistenceValues(), 'starts_at' => now()->subMinute(),
            'status' => 'active', 'granted_by_user_account_id' => $admin->getKey(), 'reason' => 'Admin UI test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
        $this->actingAs($admin)->get(route('admin.audit'))->assertRedirect(route('account.security'));
        $admin->forceFill([
            'two_factor_secret' => encrypt('admin-ui-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['admin-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();
        DB::table('authorization_events')->insert([
            'actor_user_account_id' => $admin->getKey(), 'subject_user_account_id' => $outsider->getKey(),
            'event_type' => 'grant_created', 'target_type' => 'scoped_grant', 'target_public_id' => $outsider->public_id,
            'before_hash' => random_bytes(32), 'after_hash' => random_bytes(32), 'reason' => 'Approved support assignment.',
            'occurred_at' => now(), 'correlation_id' => '11111111-1111-4111-8111-111111111111',
        ]);

        $this->actingAs($admin)->get(route('admin.audit', ['event_type' => 'grant_created']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Approved support assignment.')->assertSee($outsider->public_id)
            ->assertDontSee('before_hash')->assertDontSee('after_hash');
    }
}
