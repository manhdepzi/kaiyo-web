<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Data;

final readonly class AdminAuditDirectoryView
{
    /** @param list<array{event_type:string,target_type:string,target_public_id:?string,reason:?string,occurred_at:string,correlation_id:?string}> $events */
    public function __construct(
        public array $events,
        public ?string $eventType,
        public string $query,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
