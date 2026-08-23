<?php

declare(strict_types=1);

namespace App\Modules\Payment\Application\Data;

use DomainException;

final readonly class VerifiedProviderEvent
{
    /** @param array<string, bool|int|string|null> $redactedPayload */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $paymentPublicId,
        public string $providerTransactionReference,
        public string $outcome,
        public int $amount,
        public string $currency,
        public array $redactedPayload = [],
    ) {
        if (trim($eventId) === '' || trim($eventType) === '' || trim($paymentPublicId) === '' || trim($providerTransactionReference) === '' || ! in_array($outcome, ['paid', 'failed', 'unknown'], true) || $amount <= 0 || $currency !== 'VND') {
            throw new DomainException('Verified provider event is incomplete or unsupported.');
        }
    }
}
