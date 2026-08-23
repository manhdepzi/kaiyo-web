<?php

declare(strict_types=1);

namespace App\Modules\Identity\Authorization;

use InvalidArgumentException;

final readonly class AuthorizationScope
{
    private const TYPES = ['global', 'module', 'self', 'customer', 'company', 'sales_team', 'warehouse'];

    private function __construct(
        public string $type,
        public ?string $moduleCode = null,
        public ?int $customerId = null,
        public ?int $companyId = null,
        public ?int $salesTeamId = null,
        public ?int $warehouseId = null,
        public ?int $resourceOwnerUserAccountId = null,
    ) {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported authorization scope type.');
        }
    }

    public static function global(): self
    {
        return new self('global');
    }

    public static function module(string $moduleCode): self
    {
        return new self('module', moduleCode: self::moduleValue($moduleCode));
    }

    public static function owned(string $moduleCode, int $ownerUserAccountId): self
    {
        return new self('self', self::moduleValue($moduleCode), resourceOwnerUserAccountId: self::positive($ownerUserAccountId));
    }

    public static function customer(string $moduleCode, int $customerId, ?int $ownerUserAccountId = null): self
    {
        return new self('customer', self::moduleValue($moduleCode), customerId: self::positive($customerId), resourceOwnerUserAccountId: $ownerUserAccountId);
    }

    public static function company(string $moduleCode, int $companyId, ?int $ownerUserAccountId = null): self
    {
        return new self('company', self::moduleValue($moduleCode), companyId: self::positive($companyId), resourceOwnerUserAccountId: $ownerUserAccountId);
    }

    public static function salesTeam(string $moduleCode, int $salesTeamId): self
    {
        return new self('sales_team', self::moduleValue($moduleCode), salesTeamId: self::positive($salesTeamId));
    }

    public static function warehouse(string $moduleCode, int $warehouseId): self
    {
        return new self('warehouse', self::moduleValue($moduleCode), warehouseId: self::positive($warehouseId));
    }

    /** @return array<string, int|string|null> */
    public function persistenceValues(): array
    {
        return [
            'scope_type' => $this->type,
            'module_code' => $this->type === 'module' ? $this->moduleCode : null,
            'customer_id' => $this->customerId,
            'company_id' => $this->companyId,
            'sales_team_id' => $this->salesTeamId,
            'warehouse_id' => $this->warehouseId,
        ];
    }

    /** @return array<string, int|string|null> */
    public function identityValues(): array
    {
        return [
            'type' => $this->type,
            'module' => $this->moduleCode,
            'customer' => $this->customerId,
            'company' => $this->companyId,
            'sales_team' => $this->salesTeamId,
            'warehouse' => $this->warehouseId,
        ];
    }

    private static function moduleValue(string $moduleCode): string
    {
        $value = mb_strtolower(trim($moduleCode), 'UTF-8');
        if ($value === '' || mb_strlen($value) > 32) {
            throw new InvalidArgumentException('Module scope code is invalid.');
        }

        return $value;
    }

    private static function positive(int $value): int
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Scope identity must be positive.');
        }

        return $value;
    }
}
