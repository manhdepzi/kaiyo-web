<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\RoleBundle;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthorizationEventRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ProvisionLocalAdministrator extends Command
{
    protected $signature = 'admin:provision-local {email : Administrator email address}';

    protected $description = 'Create or update a verified local administrator and grant the local administrator role';

    public function handle(AuthorizationEventRecorder $events): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This bootstrap command is restricted to local and testing environments.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')), 'UTF-8');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 320) {
            $this->error('A valid email address is required.');

            return self::INVALID;
        }

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');
        if ($password !== $confirmation || mb_strlen($password) < 8) {
            $this->error('Passwords must match and contain at least 8 characters.');

            return self::INVALID;
        }

        $permissionCount = DB::transaction(function () use ($email, $password, $events): int {
            $account = UserAccount::query()->where('email_normalized', $email)->lockForUpdate()->first();
            $account ??= new UserAccount;
            $account->forceFill([
                'email_display' => $email,
                'email_normalized' => $email,
                'password_hash' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
                'disabled_at' => null,
            ])->save();

            $role = RoleBundle::query()->where('code', 'local-administrator')->lockForUpdate()->first();
            $role ??= new RoleBundle;
            $role->forceFill([
                'code' => 'local-administrator',
                'name' => 'Local Administrator',
                'status' => 'active',
                'requires_two_factor' => true,
            ])->save();

            $permissions = PermissionDefinition::query()->where('status', 'active')->orderBy('id')->get();
            $role->permissions()->sync($permissions->modelKeys());

            $scope = AuthorizationScope::global();
            $identityHash = hash('sha256', json_encode([
                'subject' => $account->getKey(),
                'permission' => null,
                'role' => $role->getKey(),
                'scope' => $scope->identityValues(),
            ], JSON_THROW_ON_ERROR), true);

            $grant = ScopedGrant::query()
                ->where('user_account_id', $account->getKey())
                ->where('status', 'active')
                ->where('identity_hash', $identityHash)
                ->lockForUpdate()
                ->first();

            if ($grant === null) {
                $grant = ScopedGrant::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'user_account_id' => $account->getKey(),
                    'role_bundle_id' => $role->getKey(),
                    'permission_definition_id' => null,
                    ...$scope->persistenceValues(),
                    'starts_at' => now(),
                    'ends_at' => null,
                    'status' => 'active',
                    'granted_by_user_account_id' => $account->getKey(),
                    'approved_by_user_account_id' => null,
                    'reason' => 'Explicit local administrator bootstrap requested by the repository owner.',
                    'identity_hash' => $identityHash,
                ]);

                $events->record(
                    'grant_created',
                    'scoped_grant',
                    $grant->public_id,
                    $account,
                    $account,
                    null,
                    $grant->auditSnapshot(),
                    'Explicit local administrator bootstrap requested by the repository owner.',
                );
            }

            return $permissions->count();
        }, 3);

        $this->info("Local administrator is active with {$permissionCount} permissions.");
        $this->warn('Two-factor authentication must be enrolled from Account > Security before admin routes can be used.');

        return self::SUCCESS;
    }
}
