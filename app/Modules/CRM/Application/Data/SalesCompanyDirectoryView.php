<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class SalesCompanyDirectoryView
{
    /**
     * @param  list<array{public_id:string,display_name:string,legal_name:string,tax_code:?string,status:string,updated_at:string}>  $companies
     * @param  array<string,int>  $statusCounts
     */
    public function __construct(
        public array $companies,
        public array $statusCounts,
        public string $query,
        public ?string $status,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
