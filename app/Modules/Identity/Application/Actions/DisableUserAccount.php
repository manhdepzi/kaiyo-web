<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Infrastructure\Persistence\Models\AuthSession;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthenticationEventRecorder;
use Illuminate\Support\Facades\DB;

final class DisableUserAccount
{
    public function __construct(private readonly AuthenticationEventRecorder $recorder) {}

    public function execute(UserAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $locked = UserAccount::query()
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'disabled') {
                return;
            }

            $locked->forceFill([
                'status' => 'disabled',
                'disabled_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            AuthSession::query()
                ->where('user_account_id', $locked->getKey())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            $this->recorder->record('account_disabled', $locked);
        }, 3);
    }
}
