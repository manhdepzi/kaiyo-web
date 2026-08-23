<?php

declare(strict_types=1);

namespace App\Modules\Payment\Domain\Events;

final readonly class PaymentVerified
{
    public function __construct(public int $paymentId, public string $operationKey, public string $evidenceReference) {}
}
