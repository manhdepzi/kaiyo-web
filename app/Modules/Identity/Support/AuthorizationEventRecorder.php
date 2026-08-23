<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AuthorizationEventRecorder
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $type,
        string $targetType,
        ?string $targetPublicId,
        ?UserAccount $actor,
        ?UserAccount $subject,
        ?array $before,
        ?array $after,
        ?string $reason,
    ): void {
        DB::table('authorization_events')->insert([
            'actor_user_account_id' => $actor?->getKey(),
            'subject_user_account_id' => $subject?->getKey(),
            'event_type' => $type,
            'target_type' => $targetType,
            'target_public_id' => $targetPublicId,
            'before_hash' => $this->snapshotHash($before),
            'after_hash' => $this->snapshotHash($after),
            'reason' => $reason,
            'occurred_at' => now(),
            'correlation_id' => $this->request->attributes->get('correlation_id'),
        ]);
    }

    /** @param array<string, mixed>|null $snapshot */
    private function snapshotHash(?array $snapshot): ?string
    {
        if ($snapshot === null) {
            return null;
        }

        ksort($snapshot);

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), true);
    }
}
