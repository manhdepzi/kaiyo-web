<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Authorization;

use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\SalesTeam;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\ScopeTargetVerifier;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;

final class DatabaseScopeTargetVerifier implements ScopeTargetVerifier
{
    public function exists(AuthorizationScope $scope): bool
    {
        return match ($scope->type) {
            'global', 'module', 'self' => true,
            'customer' => Customer::query()->whereKey($scope->customerId)->exists(),
            'company' => Company::query()->whereKey($scope->companyId)->exists(),
            'sales_team' => SalesTeam::query()->whereKey($scope->salesTeamId)->exists(),
            'warehouse' => Warehouse::query()->whereKey($scope->warehouseId)->where('status', 'active')->exists(),
            default => false,
        };
    }
}
