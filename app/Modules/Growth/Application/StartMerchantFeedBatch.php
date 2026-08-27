<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Infrastructure\Persistence\Models\MerchantFeedBatch;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class StartMerchantFeedBatch
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, string $configurationRevision, string $operationKey): MerchantFeedBatch
    {
        if (! $this->authorizer->allows($actor, 'merchant.manage', AuthorizationScope::module('system'))) {
            throw new AuthorizationException('Merchant management permission is required.');
        }
        $configurationRevision = trim($configurationRevision);
        $operationKey = trim($operationKey);
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,99}\z/', $configurationRevision) !== 1
            || mb_strlen($operationKey, 'UTF-8') < 8 || mb_strlen($operationKey, 'UTF-8') > 200) {
            throw new DomainException('Merchant batch identity is invalid.');
        }
        $operationHash = hash('sha256', $operationKey, true);

        return DB::transaction(function () use ($actor, $configurationRevision, $operationHash): MerchantFeedBatch {
            $existing = MerchantFeedBatch::query()->where('operation_key_hash', $operationHash)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->configuration_revision !== $configurationRevision) {
                    throw new DomainException('Merchant operation identity was reused with another configuration revision.');
                }

                return $existing;
            }

            return MerchantFeedBatch::query()->create([
                'configuration_revision' => $configurationRevision,
                'state' => 'pending',
                'operation_key_hash' => $operationHash,
                'requested_by_user_account_id' => $actor->getKey(),
            ]);
        }, 3);
    }
}
