<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Contracts\AnalyticsDestination;
use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Growth\Domain\AnalyticsEventPolicy;
use App\Modules\Growth\Infrastructure\Persistence\Models\AnalyticsDeliveryBatch;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DeliverAnalyticsBatch
{
    public function __construct(
        private AnalyticsDestination $destination,
        private AnalyticsEventPolicy $policy,
    ) {}

    /** @param list<AnalyticsEvent> $events */
    public function execute(
        string $destinationCode,
        string $configurationRevision,
        string $consentRevision,
        string $operationKey,
        array $events,
    ): AnalyticsDeliveryBatch {
        foreach ([$destinationCode, $configurationRevision, $consentRevision] as $value) {
            if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,99}\z/', $value) !== 1) {
                throw new DomainException('Analytics delivery configuration is invalid.');
            }
        }
        if (mb_strlen($operationKey, 'UTF-8') < 8 || mb_strlen($operationKey, 'UTF-8') > 200 || count($events) > 1000) {
            throw new DomainException('Analytics batch identity or size is invalid.');
        }
        $requestEntries = [];
        foreach ($events as $event) {
            $this->policy->validate($event);
            $requestEntries[] = hash('sha256', $event->identity).'|'.hash('sha256', json_encode($event->payload(), JSON_THROW_ON_ERROR)).'|'.($event->consentGranted ? '1' : '0');
        }
        sort($requestEntries, SORT_STRING);
        $requestHash = hash('sha256', implode('\n', $requestEntries), true);
        $operationHash = hash('sha256', $operationKey, true);
        $batch = DB::transaction(function () use ($destinationCode, $configurationRevision, $consentRevision, $operationHash, $requestHash): AnalyticsDeliveryBatch {
            $existing = AnalyticsDeliveryBatch::query()->where('operation_key_hash', $operationHash)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->destination_code !== $destinationCode || $existing->configuration_revision !== $configurationRevision
                    || $existing->consent_revision !== $consentRevision || ! hash_equals((string) $existing->request_hash, $requestHash)) {
                    throw new DomainException('Analytics operation identity was reused with another request.');
                }

                return $existing;
            }

            return AnalyticsDeliveryBatch::query()->create([
                'destination_code' => $destinationCode,
                'configuration_revision' => $configurationRevision,
                'consent_revision' => $consentRevision,
                'state' => 'pending',
                'operation_key_hash' => $operationHash,
                'request_hash' => $requestHash,
            ]);
        }, 3);

        $claimed = DB::transaction(function () use ($batch): bool {
            $locked = AnalyticsDeliveryBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state === 'completed' || $locked->state === 'running') {
                return false;
            }
            $locked->forceFill(['state' => 'running', 'started_at' => $locked->started_at ?? now(), 'completed_at' => null])->save();

            return true;
        }, 3);
        if (! $claimed) {
            return $batch->refresh();
        }

        foreach ($events as $event) {
            $this->deliverEvent($batch, $event);
        }
        $this->complete($batch);

        return $batch->refresh();
    }

    private function deliverEvent(AnalyticsDeliveryBatch $batch, AnalyticsEvent $event): void
    {
        $identityHash = hash('sha256', $event->identity, true);
        $payloadHash = hash('sha256', json_encode($event->payload(), JSON_THROW_ON_ERROR), true);
        $existing = DB::table('analytics_delivery_items')->where('destination_code', $batch->destination_code)
            ->where('event_identity_hash', $identityHash)->first();
        if ($existing !== null && ((int) $existing->analytics_delivery_batch_id !== $batch->getKey()
            || in_array($existing->outcome, ['succeeded', 'suppressed'], true))) {
            return;
        }
        if (! $event->consentGranted) {
            $this->storeItem($batch, $event, $identityHash, $payloadHash, 'suppressed', null, 'consent_denied', false);

            return;
        }
        try {
            $result = $this->destination->publish($event, hash('sha256', $batch->destination_code.'|'.$event->identity));
            if (($result->succeeded && $result->reference === null) || (! $result->succeeded && $result->errorCode === null)) {
                throw new DomainException('Analytics destination returned an invalid outcome.');
            }
            $this->storeItem(
                $batch,
                $event,
                $identityHash,
                $payloadHash,
                $result->succeeded ? 'succeeded' : 'failed',
                $result->reference,
                $result->errorCode,
                true,
            );
        } catch (Throwable) {
            $this->storeItem($batch, $event, $identityHash, $payloadHash, 'failed', null, 'destination_failure', true);
        }
    }

    private function storeItem(AnalyticsDeliveryBatch $batch, AnalyticsEvent $event, string $identityHash, string $payloadHash, string $outcome, ?string $reference, ?string $errorCode, bool $attempted): void
    {
        DB::transaction(function () use ($batch, $event, $identityHash, $payloadHash, $outcome, $reference, $errorCode, $attempted): void {
            $existing = DB::table('analytics_delivery_items')->where('destination_code', $batch->destination_code)
                ->where('event_identity_hash', $identityHash)->lockForUpdate()->first();
            if ($existing !== null && (int) $existing->analytics_delivery_batch_id !== $batch->getKey()) {
                return;
            }
            $values = [
                'event_type' => $event->type,
                'subject_type' => $event->subjectType,
                'subject_public_id' => $event->subjectPublicId,
                'payload_hash' => $payloadHash,
                'consent_granted' => $event->consentGranted,
                'outcome' => $outcome,
                'destination_reference' => $reference === null ? null : mb_substr($reference, 0, 255),
                'error_code' => $errorCode === null ? null : mb_substr($errorCode, 0, 100),
                'attempt_count' => (int) ($existing->attempt_count ?? 0) + ($attempted ? 1 : 0),
                'occurred_at' => $event->occurredAt,
                'last_attempted_at' => $attempted ? now() : null,
                'updated_at' => now(),
            ];
            if ($existing === null) {
                DB::table('analytics_delivery_items')->insert([
                    'analytics_delivery_batch_id' => $batch->getKey(),
                    'destination_code' => $batch->destination_code,
                    'event_identity_hash' => $identityHash,
                    ...$values,
                    'created_at' => now(),
                ]);

                return;
            }
            DB::table('analytics_delivery_items')->where('id', $existing->id)->update($values);
        }, 3);
    }

    private function complete(AnalyticsDeliveryBatch $batch): void
    {
        $counts = DB::table('analytics_delivery_items')->where('analytics_delivery_batch_id', $batch->getKey())
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw("SUM(CASE WHEN outcome = 'succeeded' THEN 1 ELSE 0 END) AS succeeded_count")
            ->selectRaw("SUM(CASE WHEN outcome = 'suppressed' THEN 1 ELSE 0 END) AS suppressed_count")
            ->selectRaw("SUM(CASE WHEN outcome = 'failed' THEN 1 ELSE 0 END) AS failed_count")->first();
        $total = (int) ($counts->total_count ?? 0);
        $succeeded = (int) ($counts->succeeded_count ?? 0);
        $suppressed = (int) ($counts->suppressed_count ?? 0);
        $failed = (int) ($counts->failed_count ?? 0);
        $state = $failed === 0 ? 'completed' : (($succeeded + $suppressed) === 0 ? 'failed' : 'partial');
        $batch->forceFill([
            'state' => $state,
            'total_count' => $total,
            'succeeded_count' => $succeeded,
            'suppressed_count' => $suppressed,
            'failed_count' => $failed,
            'completed_at' => now(),
        ])->save();
    }
}
