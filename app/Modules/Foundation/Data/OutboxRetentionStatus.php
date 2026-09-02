<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class OutboxRetentionStatus
{
    /**
     * @param  list<array{event_type:string,retention_days:?int,published_count:int,eligible_count:int}>  $facts
     */
    public function __construct(
        public array $facts,
        public int $nonTerminalCount,
    ) {}

    public function complete(): bool
    {
        return collect($this->facts)->every(fn (array $fact): bool => $fact['retention_days'] !== null);
    }

    /** @return array{policy_complete:bool,non_terminal_count:int,facts:list<array{event_type:string,retention_days:?int,published_count:int,eligible_count:int}>} */
    public function toArray(): array
    {
        return [
            'policy_complete' => $this->complete(),
            'non_terminal_count' => $this->nonTerminalCount,
            'facts' => $this->facts,
        ];
    }
}
