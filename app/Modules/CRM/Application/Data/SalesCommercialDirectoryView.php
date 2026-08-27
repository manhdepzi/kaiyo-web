<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class SalesCommercialDirectoryView
{
    /** @param list<array{public_id:string,party:string,state:string,amount:int,currency:string,detail:string,occurred_at:string}> $records */
    public function __construct(
        public array $records,
        public string $query,
        public ?string $status,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
