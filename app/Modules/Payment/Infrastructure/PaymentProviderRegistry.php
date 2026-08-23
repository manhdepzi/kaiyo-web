<?php

declare(strict_types=1);

namespace App\Modules\Payment\Infrastructure;

use App\Modules\Payment\Contracts\PaymentProviderAdapter;
use DomainException;

final class PaymentProviderRegistry
{
    /** @var array<string, PaymentProviderAdapter> */
    private array $adapters = [];

    /** @param iterable<PaymentProviderAdapter> $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $code = trim($adapter->code());
            if ($code === '' || isset($this->adapters[$code])) {
                throw new DomainException('Payment provider adapter code is invalid or duplicated.');
            }
            $this->adapters[$code] = $adapter;
        }
    }

    public function resolve(string $code): PaymentProviderAdapter
    {
        $adapter = $this->adapters[$code] ?? null;
        if ($adapter === null) {
            throw new DomainException('Payment provider capability is disabled or unconfigured.');
        }

        return $adapter;
    }
}
