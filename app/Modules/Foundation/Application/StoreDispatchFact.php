<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\DispatchFact;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StoreDispatchFact
{
    public function __construct(private readonly DispatchFactCatalog $catalog) {}

    public function record(DispatchFact $fact): void
    {
        $this->catalog->validate($fact);
        if (! preg_match('/\A[a-z][a-z0-9._-]{2,99}\z/', $fact->type)
            || ! preg_match('/\A[a-z][a-z0-9._-]{1,49}\z/', $fact->aggregateType)
            || $fact->identity === '' || strlen($fact->identity) > 200
            || $fact->aggregatePublicId === '' || strlen($fact->aggregatePublicId) > 100) {
            throw new DomainException('Dispatch fact identity is invalid.');
        }

        $payload = $fact->payload;
        ksort($payload);
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $identityHash = hash('sha256', $fact->identity, true);
        $payloadHash = hash('sha256', $encoded, true);
        $inserted = DB::table('dispatch_records')->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'event_identity_hash' => $identityHash,
            'event_type' => $fact->type,
            'event_version' => $fact->version,
            'aggregate_type' => $fact->aggregateType,
            'aggregate_public_id' => $fact->aggregatePublicId,
            'payload' => $encoded,
            'payload_hash' => $payloadHash,
            'state' => 'pending',
            'attempt_count' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 1) {
            return;
        }

        $existing = DB::table('dispatch_records')->where('event_identity_hash', $identityHash)->first();
        if ($existing === null
            || $existing->event_type !== $fact->type
            || (int) $existing->event_version !== $fact->version
            || $existing->aggregate_type !== $fact->aggregateType
            || $existing->aggregate_public_id !== $fact->aggregatePublicId
            || ! is_string($existing->payload_hash)
            || ! hash_equals($existing->payload_hash, $payloadHash)) {
            throw new DomainException('Dispatch fact identity was reused with conflicting content.');
        }
    }
}
