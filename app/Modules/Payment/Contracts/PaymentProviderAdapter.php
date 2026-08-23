<?php

declare(strict_types=1);

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\Application\Data\VerifiedProviderEvent;

interface PaymentProviderAdapter
{
    public function code(): string;

    /** @param array<string, string> $headers */
    public function verifyWebhook(string $rawBody, array $headers): VerifiedProviderEvent;
}
