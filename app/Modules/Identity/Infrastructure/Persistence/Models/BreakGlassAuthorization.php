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
 * @property int $requester_user_account_id
 * @property int|null $approver_user_account_id
 * @property int $permission_definition_id
 * @property string $scope_type
 * @property string|null $module_code
 * @property int|null $customer_id
 * @property int|null $company_id
 * @property int|null $sales_team_id
 * @property int|null $warehouse_id
 * @property string $reason
 * @property string $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $reviewed_at
 * @property int $lock_version
 * @property-read PermissionDefinition $permission
 */
final class BreakGlassAuthorization extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $authorization): void {
            $authorization->public_id = $authorization->public_id ?: (string) Str::ulid();
        });
    }

    /** @return BelongsTo<PermissionDefinition, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(PermissionDefinition::class, 'permission_definition_id');
    }

    public function toAuthorizationScope(): AuthorizationScope
    {
        return match ($this->scope_type) {
            'global' => AuthorizationScope::global(),
            'module' => AuthorizationScope::module((string) $this->module_code),
            'self' => AuthorizationScope::owned('identity', $this->requester_user_account_id),
            'customer' => AuthorizationScope::customer('crm', (int) $this->customer_id),
            'company' => AuthorizationScope::company('crm', (int) $this->company_id),
            'sales_team' => AuthorizationScope::salesTeam('crm', (int) $this->sales_team_id),
            'warehouse' => AuthorizationScope::warehouse('inventory', (int) $this->warehouse_id),
            default => throw new \DomainException('Stored break-glass scope is invalid.'),
        };
    }

    /** @return array<string, int|string|null> */
    public function auditSnapshot(): array
    {
        return [
            'requester_user_account_id' => $this->requester_user_account_id,
            'approver_user_account_id' => $this->approver_user_account_id,
            'permission_definition_id' => $this->permission_definition_id,
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

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
