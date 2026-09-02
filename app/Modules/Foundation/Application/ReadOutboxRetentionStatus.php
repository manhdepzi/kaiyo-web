<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\OutboxRetentionStatus;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReadOutboxRetentionStatus
{
    /** @var list<string> */
    private const FACT_TYPES = [
        'catalog.projection.changed',
        'commerce.order.placed',
        'commerce.order.state.changed',
        'inventory.availability.changed',
        'payment.verified',
        'quotation.revision.state.changed',
        'shipping.shipment.state.changed',
    ];

    public function execute(): OutboxRetentionStatus
    {
        $policies = $this->policies();
        $facts = [];
        foreach (self::FACT_TYPES as $eventType) {
            $days = $policies[$eventType];
            $published = DB::table('dispatch_records')->where('event_type', $eventType)->where('state', 'published');
            $facts[] = [
                'event_type' => $eventType,
                'retention_days' => $days,
                'published_count' => (clone $published)->count(),
                'eligible_count' => $days === null ? 0 : (clone $published)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', Carbon::now()->subDays($days))
                    ->count(),
            ];
        }

        return new OutboxRetentionStatus(
            facts: $facts,
            nonTerminalCount: DB::table('dispatch_records')->where('state', '!=', 'published')->count(),
        );
    }

    /** @return array<string, int|null> */
    private function policies(): array
    {
        $configured = config('outbox.retention_days');
        if (! is_array($configured) || array_keys($configured) !== self::FACT_TYPES) {
            throw new DomainException('Outbox retention policy must define exactly every approved fact type.');
        }

        $policies = [];
        foreach ($configured as $eventType => $value) {
            if ($value === null || $value === '') {
                $policies[$eventType] = null;

                continue;
            }
            if ((is_string($value) && ctype_digit($value)) || is_int($value)) {
                $days = (int) $value;
                if ($days >= 1) {
                    $policies[$eventType] = $days;

                    continue;
                }
            }
            throw new DomainException("Outbox retention for {$eventType} must be a positive integer number of days or remain unset.");
        }

        return $policies;
    }
}
