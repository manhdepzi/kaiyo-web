<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class SalesCompanyView
{
    /** @param list<array{account_public_id:string,email:string,status:string,starts_at:string,ends_at:?string}> $members */
    public function __construct(
        public string $publicId,
        public string $legalName,
        public string $displayName,
        public ?string $taxCode,
        public string $status,
        public int $version,
        public array $members,
        public bool $canManageMembers,
    ) {}
}
