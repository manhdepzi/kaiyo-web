<?php

declare(strict_types=1);

namespace App\Modules\Payment\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Order\Infrastructure\Persistence\Models\CancellationRequest;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Payment\Infrastructure\Persistence\Models\PaymentAttempt;
use App\Modules\Payment\Infrastructure\Persistence\Models\Refund;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ManageFullRefund
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function propose(Refund $refund, UserAccount $actor, string $reason, string $requestKey, int $expectedVersion): Refund
    {
        $this->authorize($actor, 'payments.refund_propose');
        if (trim($reason) === '' || mb_strlen($reason) > 1000 || trim($requestKey) === '') {
            throw new DomainException('Refund proposal identity and reason are required.');
        }
        $hash = hash('sha256', $refund->public_id."\0".$reason, true);

        return DB::transaction(function () use ($refund, $actor, $reason, $requestKey, $expectedVersion, $hash): Refund {
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state === 'proposed' && $locked->request_key === $requestKey && hash_equals((string) $locked->request_hash, $hash)) {
                return $locked;
            }
            if ($locked->state !== 'required' || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Refund proposal is stale or ineligible.');
            }
            $cancellation = CancellationRequest::query()->findOrFail($locked->cancellation_request_id);
            if ($cancellation->state !== 'approved') {
                throw new DomainException('Full refund requires an approved pre-dispatch cancellation.');
            }
            $locked->forceFill(['state' => 'proposed', 'request_key' => $requestKey, 'request_hash' => $hash, 'proposed_by_user_account_id' => $actor->getKey(), 'reason' => $reason, 'lock_version' => $expectedVersion + 1])->save();

            return $locked->refresh();
        }, 3);
    }

    public function approve(Refund $refund, UserAccount $actor, int $expectedVersion): Refund
    {
        $this->authorize($actor, 'payments.refund_approve');

        return DB::transaction(function () use ($refund, $actor, $expectedVersion): Refund {
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state === 'approved' && $locked->approved_by_user_account_id === $actor->getKey()) {
                return $locked;
            }
            if ($locked->state !== 'proposed' || $locked->lock_version !== $expectedVersion || $locked->proposed_by_user_account_id === $actor->getKey()) {
                throw new DomainException('Refund approval is stale or violates separation of duties.');
            }
            $locked->forceFill(['state' => 'approved', 'approved_by_user_account_id' => $actor->getKey(), 'lock_version' => $expectedVersion + 1])->save();

            return $locked->refresh();
        }, 3);
    }

    public function complete(Refund $refund, string $operationKey, string $verifiedReference): Refund
    {
        if (trim($operationKey) === '' || trim($verifiedReference) === '') {
            throw new DomainException('Verified refund evidence is required.');
        }

        return DB::transaction(function () use ($refund, $operationKey, $verifiedReference): Refund {
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();
            $attempt = PaymentAttempt::query()->where('payment_id', $payment->getKey())->orderByDesc('attempt_no')->lockForUpdate()->firstOrFail();
            $existing = DB::table('payment_transactions')->where('operation_key', $operationKey)->first();
            if ($existing !== null) {
                if ($existing->type !== 'refund' || (int) $existing->amount !== $locked->amount) {
                    throw new DomainException('Refund operation key conflicts with existing evidence.');
                }

                return $locked;
            }
            if ($locked->state !== 'approved' || $payment->state !== 'paid' || $locked->amount !== $payment->paid_amount || $payment->refunded_amount !== 0) {
                throw new DomainException('Only one approved full refund of verified paid balance is allowed in V1.');
            }
            DB::table('payment_transactions')->insert([
                'payment_attempt_id' => $attempt->getKey(), 'type' => 'refund', 'amount' => $locked->amount,
                'currency' => $locked->currency, 'provider_transaction_ref_hash' => hash('sha256', "refund\0".$verifiedReference, true),
                'operation_key' => $operationKey, 'evidence' => json_encode(['reference_hash' => hash('sha256', $verifiedReference)], JSON_THROW_ON_ERROR),
                'verified_at' => now(), 'created_at' => now(),
            ]);
            $payment->forceFill(['state' => 'refunded', 'refunded_amount' => $locked->amount, 'refunded_at' => now(), 'lock_version' => $payment->lock_version + 1])->save();
            $locked->forceFill(['state' => 'completed', 'provider_ref_hash' => hash('sha256', $verifiedReference, true), 'completed_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();

            return $locked->refresh();
        }, 3);
    }

    private function authorize(UserAccount $actor, string $permission): void
    {
        if (! $this->authorizer->allowsPersistent($actor, $permission, AuthorizationScope::module('payments'))) {
            throw new AuthorizationException('Payment refund permission denied.');
        }
    }
}
