<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Data;

use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;
use DomainException;

final readonly class CheckoutCommand
{
    public function __construct(
        public Cart $cart,
        public string $operationKey,
        public AddressData $shippingAddress,
        public AddressData $billingAddress,
        public string $shippingMethod,
        public string $paymentMethod,
        public bool $invoiceRequested = false,
    ) {
        if (trim($operationKey) === '' || strlen($operationKey) > 100) {
            throw new DomainException('A bounded checkout idempotency key is required.');
        }
        if (trim($shippingMethod) === '' || strlen($shippingMethod) > 100) {
            throw new DomainException('A shipping method is required.');
        }
        if (! in_array($paymentMethod, ['cod', 'bank_transfer', 'online_gateway'], true)) {
            throw new DomainException('Unsupported checkout payment method.');
        }
    }

    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'cart' => $this->cart->public_id,
            'shipping_address' => $this->shippingAddress->snapshot(),
            'billing_address' => $this->billingAddress->snapshot(),
            'shipping_method' => $this->shippingMethod,
            'payment_method' => $this->paymentMethod,
            'invoice_requested' => $this->invoiceRequested,
        ], JSON_THROW_ON_ERROR), true);
    }
}
