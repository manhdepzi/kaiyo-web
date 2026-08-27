<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class SalesLeadDirectoryView
{
    /**
     * @param  list<array{public_id:string,display_name:string,company:?string,email:?string,phone:?string,source:string,status:string,updated_at:string}>  $leads
     * @param  array<string,int>  $statusCounts
     */
    public function __construct(
        public array $leads,
        public array $statusCounts,
        public string $query,
        public ?string $status,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
