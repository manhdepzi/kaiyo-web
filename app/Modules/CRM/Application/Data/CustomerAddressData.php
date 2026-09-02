<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

use DomainException;

final readonly class CustomerAddressData
{
    public string $label;

    public string $recipientName;

    public string $addressLine1;

    public string $countryCode;

    public ?string $companyName;

    public ?string $taxCode;

    public ?string $addressLine2;

    public ?string $locality;

    public ?string $subdivision;

    public ?string $postalCode;

    public ?string $phone;

    public function __construct(
        string $label,
        string $recipientName,
        string $addressLine1,
        string $countryCode = 'VN',
        ?string $companyName = null,
        ?string $taxCode = null,
        ?string $addressLine2 = null,
        ?string $locality = null,
        ?string $subdivision = null,
        ?string $postalCode = null,
        ?string $phone = null,
        public bool $defaultShipping = false,
        public bool $defaultBilling = false,
    ) {
        $this->label = $this->required($label, 100, 'Address label');
        $this->recipientName = $this->required($recipientName, 200, 'Recipient name');
        $this->addressLine1 = $this->required($addressLine1, 500, 'Address line');
        $this->countryCode = mb_strtoupper(trim($countryCode), 'UTF-8');
        if (preg_match('/^[A-Z]{2}$/', $this->countryCode) !== 1) {
            throw new DomainException('Address country code is invalid.');
        }
        $this->companyName = $this->optional($companyName, 255, 'Company name');
        $this->taxCode = $this->optional($taxCode, 64, 'Tax code');
        $this->addressLine2 = $this->optional($addressLine2, 500, 'Address line 2');
        $this->locality = $this->optional($locality, 160, 'Locality');
        $this->subdivision = $this->optional($subdivision, 160, 'Subdivision');
        $this->postalCode = $this->optional($postalCode, 32, 'Postal code');
        $this->phone = $this->optional($phone, 32, 'Phone');
    }

    /** @return array<string, bool|string|null> */
    public function values(): array
    {
        return [
            'label' => $this->label,
            'recipient_name' => $this->recipientName,
            'company_name' => $this->companyName,
            'tax_code' => $this->taxCode,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'locality' => $this->locality,
            'subdivision' => $this->subdivision,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'phone' => $this->phone,
            'is_default_shipping' => $this->defaultShipping,
            'is_default_billing' => $this->defaultBilling,
        ];
    }

    private function required(string $value, int $max, string $field): string
    {
        $normalized = trim($value);
        if ($normalized === '' || mb_strlen($normalized) > $max) {
            throw new DomainException($field.' is invalid.');
        }

        return $normalized;
    }

    private function optional(?string $value, int $max, string $field): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $normalized = trim($value);
        if (mb_strlen($normalized) > $max) {
            throw new DomainException($field.' is invalid.');
        }

        return $normalized;
    }
}
