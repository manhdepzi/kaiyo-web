<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Services;

use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\ShippingPreparation;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use App\Modules\Checkout\Contracts\ShippingRegistrationPort;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Checkout\Infrastructure\Persistence\Models\OrderLine;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use App\Modules\Shipping\Infrastructure\Persistence\Models\ShipmentItem;
use DomainException;

final class ShippingConfigurationService implements ShippingPreparationPort, ShippingRegistrationPort
{
    public function prepare(string $method, AddressData $address, int $merchandiseAmount, string $currency): ShippingPreparation
    {
        $methods = config('shipping.methods');
        $configuration = is_array($methods) ? ($methods[$method] ?? null) : null;
        if (! is_array($configuration) || ($configuration['enabled'] ?? false) !== true || $currency !== 'VND' || $merchandiseAmount < 0) {
            throw new DomainException('Shipping method is disabled or unconfigured.');
        }
        $amount = $configuration['amount'] ?? null;
        $type = $configuration['type'] ?? null;
        $carrier = $configuration['carrier_code'] ?? null;
        if (! is_int($amount) || $amount < 0 || ! in_array($type, ['configured', 'manual'], true) || ($carrier !== null && (! is_string($carrier) || trim($carrier) === ''))) {
            throw new DomainException('Shipping method configuration is invalid.');
        }
        $freeThreshold = $configuration['free_threshold'] ?? null;
        if ($freeThreshold !== null && (! is_int($freeThreshold) || $freeThreshold <= 0)) {
            throw new DomainException('Shipping free threshold configuration is invalid.');
        }
        $effectiveAmount = is_int($freeThreshold) && $merchandiseAmount >= $freeThreshold ? 0 : $amount;

        return new ShippingPreparation($method, $effectiveAmount, (string) config('shipping.revision'), ['type' => $type, 'carrier_code' => $carrier]);
    }

    public function register(Order $order): void
    {
        $snapshot = $order->shipping_preparation;
        $metadata = $snapshot['metadata'] ?? null;
        $carrier = is_array($metadata) && is_string($metadata['carrier_code'] ?? null) ? $metadata['carrier_code'] : null;
        $revision = is_string($snapshot['configuration_revision'] ?? null) ? $snapshot['configuration_revision'] : '';
        if ($revision === '' || $order->shipping_method === '') {
            throw new DomainException('Order shipping snapshot cannot initialize Shipment.');
        }
        $shipment = Shipment::query()->firstOrCreate(
            ['order_id' => $order->getKey()],
            ['method_code' => $order->shipping_method, 'configuration_revision' => $revision, 'carrier_code' => $carrier, 'state' => 'draft'],
        );
        if ($shipment->method_code !== $order->shipping_method || $shipment->configuration_revision !== $revision || $shipment->carrier_code !== $carrier) {
            throw new DomainException('Existing Shipment conflicts with immutable Order shipping snapshot.');
        }
        $lines = OrderLine::query()->where('order_id', $order->getKey())->orderBy('id')->get();
        if ($lines->isEmpty()) {
            throw new DomainException('Shipment requires Order lines.');
        }
        foreach ($lines as $line) {
            ShipmentItem::query()->firstOrCreate(
                ['order_line_id' => $line->getKey()],
                ['shipment_id' => $shipment->getKey(), 'quantity' => $line->quantity, 'created_at' => now()],
            );
        }
    }
}
