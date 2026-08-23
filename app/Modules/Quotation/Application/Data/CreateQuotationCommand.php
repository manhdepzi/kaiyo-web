<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Data;

use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;

final readonly class CreateQuotationCommand
{
    /**
     * @param  list<array{variant_id:int, quantity:string, negotiated_unit_amount?:int|null, cost_unit_amount?:int|null}>  $lines
     * @param  array<string, bool|int|string|null>  $commercialTerms
     */
    public function __construct(
        public ?int $customerId,
        public ?int $companyId,
        public ?string $guestAccessToken,
        public array $lines,
        public AddressData $billingAddress,
        public AddressData $shippingAddress,
        public string $shippingMethod,
        public int $validityDays,
        public array $commercialTerms,
        public string $operationKey,
        public ?UserAccount $proposer = null,
        public ?string $abuseKey = null,
        public string $paymentMethod = 'cod',
        public bool $invoiceRequested = false,
    ) {
        if (($customerId === null) === ($guestAccessToken === null) || ($guestAccessToken !== null && strlen($guestAccessToken) < 32)) {
            throw new DomainException('Quotation requires exactly one secure Customer or guest identity.');
        }
        if ($guestAccessToken !== null && $companyId !== null) {
            throw new DomainException('Guest quotation cannot claim an existing Company identity.');
        }
        if ($lines === [] || count($lines) > 200 || trim($operationKey) === '' || strlen($operationKey) > 100) {
            throw new DomainException('Quotation lines or operation identity are invalid.');
        }
        if ($validityDays < 1 || $validityDays > (int) config('quotation.maximum_validity_days')) {
            throw new DomainException('Quotation validity is outside the approved bounds.');
        }
        if (! in_array($paymentMethod, ['cod', 'bank_transfer', 'online_gateway'], true)) {
            throw new DomainException('Unsupported quotation payment method.');
        }
        if ($guestAccessToken !== null && ($abuseKey === null || strlen($abuseKey) < 16 || strlen($abuseKey) > 200)) {
            throw new DomainException('Guest quotation requires a trusted anti-abuse context.');
        }
    }

    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'customer' => $this->customerId, 'company' => $this->companyId,
            'guest' => $this->guestAccessToken === null ? null : hash('sha256', $this->guestAccessToken),
            'lines' => $this->lines, 'billing' => $this->billingAddress->snapshot(), 'shipping' => $this->shippingAddress->snapshot(),
            'shipping_method' => $this->shippingMethod, 'validity_days' => $this->validityDays, 'terms' => $this->commercialTerms,
            'payment_method' => $this->paymentMethod, 'invoice_requested' => $this->invoiceRequested,
            'proposer' => $this->proposer?->public_id,
            'abuse_context' => $this->abuseKey === null ? null : hash('sha256', $this->abuseKey),
        ], JSON_THROW_ON_ERROR), true);
    }
}
