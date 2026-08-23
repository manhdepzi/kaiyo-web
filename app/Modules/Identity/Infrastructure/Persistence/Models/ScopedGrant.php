<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use App\Modules\Identity\Authorization\AuthorizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $user_account_id
 * @property int|null $role_bundle_id
 * @property int|null $permission_definition_id
 * @property string $scope_type
 * @property string|null $module_code
 * @property int|null $customer_id
 * @property int|null $company_id
 * @property int|null $sales_team_id
 * @property int|null $warehouse_id
 * @property string $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $revoked_at
 * @property int $lock_version
 * @property-read PermissionDefinition|null $permission
 * @property-read RoleBundle|null $role
 */
final class ScopedGrant extends Model
{
    protected $guarded = [];

    protected $hidden = ['identity_hash', 'active_identity_hash'];

    protected static function booted(): void
    {
        self::creating(function (self $grant): void {
            $grant->public_id = $grant->public_id ?: (string) Str::ulid();
        });
    }

    /** @return BelongsTo<PermissionDefinition, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(PermissionDefinition::class, 'permission_definition_id');
    }

    /** @return BelongsTo<RoleBundle, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleBundle::class, 'role_bundle_id');
    }

    /** @return array<string, int|string|null> */
    public function auditSnapshot(): array
    {
        return [
            'user_account_id' => $this->user_account_id,
            'permission_definition_id' => $this->permission_definition_id,
            'role_bundle_id' => $this->role_bundle_id,
            'scope_type' => $this->scope_type,
            'module_code' => $this->module_code,
            'customer_id' => $this->customer_id,
            'company_id' => $this->company_id,
            'sales_team_id' => $this->sales_team_id,
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status,
            'lock_version' => $this->lock_version,
        ];
    }

    public function toAuthorizationScope(): AuthorizationScope
    {
        return match ($this->scope_type) {
            'global' => AuthorizationScope::global(),
            'module' => AuthorizationScope::module((string) $this->module_code),
            'self' => AuthorizationScope::owned('identity', $this->user_account_id),
            'customer' => AuthorizationScope::customer('crm', (int) $this->customer_id),
            'company' => AuthorizationScope::company('crm', (int) $this->company_id),
            'sales_team' => AuthorizationScope::salesTeam('crm', (int) $this->sales_team_id),
            'warehouse' => AuthorizationScope::warehouse('inventory', (int) $this->warehouse_id),
            default => throw new \DomainException('Stored authorization scope is invalid.'),
        };
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
