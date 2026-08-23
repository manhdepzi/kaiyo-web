<?php

declare(strict_types=1);

namespace App\Modules\Payment\Application\Actions;

use App\Modules\Payment\Application\Services\PaymentLifecycleService;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Payment\Infrastructure\Persistence\Models\PaymentAttempt;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileUnknownPayment
{
    public function __construct(private PaymentLifecycleService $payments) {}

    public function execute(Payment $payment, string $outcome, string $operationKey, string $verifiedReference): Payment
    {
        if (! in_array($outcome, ['paid', 'failed'], true) || trim($operationKey) === '' || trim($verifiedReference) === '') {
            throw new DomainException('Authenticated reconciliation outcome is required.');
        }
        if ($payment->state !== 'unknown') {
            throw new DomainException('Only Unknown Payment can be reconciled.');
        }
        if ($outcome === 'paid') {
            $result = $this->payments->recordVerifiedCharge($payment, $operationKey, $verifiedReference, 'reconciliation');
        } else {
            $result = DB::transaction(function () use ($payment): Payment {
                $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->state !== 'unknown') {
                    throw new DomainException('Reconciliation target changed state.');
                }
                $attempt = PaymentAttempt::query()->where('payment_id', $locked->getKey())->orderByDesc('attempt_no')->lockForUpdate()->firstOrFail();
                $attempt->forceFill(['state' => 'failed', 'lock_version' => $attempt->lock_version + 1])->save();
                $locked->forceFill(['state' => 'failed', 'lock_version' => $locked->lock_version + 1])->save();

                return $locked->refresh();
            }, 3);
        }
        DB::table('reconciliation_cases')->where('subject_type', 'payment')->where('subject_id', $payment->getKey())->where('state', 'open')->update([
            'state' => 'resolved',
            'resolution_evidence' => json_encode(['outcome' => $outcome, 'reference_hash' => hash('sha256', $verifiedReference)], JSON_THROW_ON_ERROR),
            'resolved_at' => now(), 'lock_version' => DB::raw('lock_version + 1'), 'updated_at' => now(),
        ]);

        return $result->refresh();
    }
}
