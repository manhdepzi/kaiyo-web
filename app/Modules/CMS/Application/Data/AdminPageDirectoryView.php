<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Data;

final readonly class AdminPageDirectoryView
{
    /**
     * @param  list<array{public_id:string,slug:string,status:string,title:string,revision_no:int,lock_version:int,has_published_revision:bool,published_at:?string,updated_at:string,media:list<array{public_id:string,original_name:string,purpose:string,sort_order:int}>}>  $pages
     * @param  array<string,int>  $statusCounts
     */
    public function __construct(
        public array $pages,
        public array $statusCounts,
        public string $query,
        public ?string $status,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
