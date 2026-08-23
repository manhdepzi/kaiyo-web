<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Infrastructure;

use App\Modules\Shipping\Contracts\CarrierAdapter;
use DomainException;

final class CarrierRegistry
{
    /** @var array<string, CarrierAdapter> */
    private array $adapters = [];

    /** @param iterable<CarrierAdapter> $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $code = trim($adapter->code());
            if ($code === '' || isset($this->adapters[$code])) {
                throw new DomainException('Carrier adapter code is invalid or duplicated.');
            }
            $this->adapters[$code] = $adapter;
        }
    }

    public function resolve(string $code): CarrierAdapter
    {
        $adapter = $this->adapters[$code] ?? null;
        if ($adapter === null) {
            throw new DomainException('Carrier capability is disabled or unconfigured.');
        }

        return $adapter;
    }
}
