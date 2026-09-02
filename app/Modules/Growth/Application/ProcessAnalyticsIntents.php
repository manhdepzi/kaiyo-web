<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Growth\Data\AnalyticsIntentData;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ProcessAnalyticsIntents
{
    private const MAX_ATTEMPTS = 5;

    private const STALE_LEASE_MINUTES = 30;

    public function __construct(private DeliverAnalyticsBatch $delivery) {}

    public function execute(int $limit = 25): int
    {
        if ($limit < 1 || $limit > 100) {
            throw new DomainException('Analytics intent processing limit must be between 1 and 100.');
        }
        $ids = DB::table('analytics_event_intents')->where(function ($query): void {
            $query->where(fn ($pending) => $pending->where('state', 'pending')->where('available_at', '<=', now()))
                ->orWhere(fn ($stale) => $stale->where('state', 'processing')
                    ->where('last_attempted_at', '<=', now()->subMinutes(self::STALE_LEASE_MINUTES)));
        })->orderBy('id')->limit($limit)->pluck('id');

        $processed = 0;
        foreach ($ids as $id) {
            $row = $this->claim((int) $id);
            if ($row === null) {
                continue;
            }
            $this->process($row);
            $processed++;
        }

        return $processed;
    }

    private function claim(int $id): ?AnalyticsIntentData
    {
        return DB::transaction(function () use ($id): ?AnalyticsIntentData {
            $row = DB::table('analytics_event_intents')->where('id', $id)->lockForUpdate()->first();
            if ($row === null) {
                return null;
            }
            $pending = $row->state === 'pending' && ! now()->isBefore($row->available_at);
            $stale = $row->state === 'processing' && $row->last_attempted_at !== null
                && ! now()->subMinutes(self::STALE_LEASE_MINUTES)->isBefore($row->last_attempted_at);
            if (! $pending && ! $stale) {
                return null;
            }
            DB::table('analytics_event_intents')->where('id', $id)->update([
                'state' => 'processing', 'attempt_count' => (int) $row->attempt_count + 1,
                'last_attempted_at' => now(), 'last_error_code' => null, 'updated_at' => now(),
            ]);

            return AnalyticsIntentData::fromDatabaseRow((array) DB::table('analytics_event_intents')->where('id', $id)->firstOrFail());
        }, 3);
    }

    private function process(AnalyticsIntentData $row): void
    {
        try {
            $event = new AnalyticsEvent(
                $row->eventIdentity,
                $row->eventType,
                $row->subjectType,
                $row->subjectPublicId,
                $row->occurredAt,
                true,
                $row->attributes,
                $row->consentEvidencePublicId,
            );
            $batch = $this->delivery->execute(
                (string) config('analytics.destination_code'),
                (string) config('analytics.configuration_revision'),
                (string) config('analytics.consent_policy_revision'),
                'analytics-intent:'.$row->publicId,
                [$event],
            );
            if ($batch->state === 'completed') {
                $this->finish($row, true, null);

                return;
            }
            $this->finish($row, false, 'destination_failure');
        } catch (Throwable $exception) {
            $this->finish($row, false, $exception instanceof DomainException ? 'contract_failure' : 'processor_failure');
        }
    }

    private function finish(AnalyticsIntentData $row, bool $completed, ?string $error): void
    {
        $dead = ! $completed && $row->attemptCount >= self::MAX_ATTEMPTS;
        DB::table('analytics_event_intents')->where('id', $row->id)->update([
            'state' => $completed ? 'completed' : ($dead ? 'dead' : 'pending'),
            'last_error_code' => $error,
            'available_at' => $completed || $dead ? now() : now()->addMinutes(min(2 ** $row->attemptCount, 60)),
            'completed_at' => $completed || $dead ? now() : null,
            'updated_at' => now(),
        ]);
    }
}
