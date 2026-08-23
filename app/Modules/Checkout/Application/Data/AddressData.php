<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Data;

use DomainException;

final readonly class AddressData
{
    public function __construct(
        public string $recipientName,
        public string $addressLine1,
        public string $countryCode,
        public ?string $companyName = null,
        public ?string $taxCode = null,
        public ?string $addressLine2 = null,
        public ?string $locality = null,
        public ?string $subdivision = null,
        public ?string $postalCode = null,
        public ?string $phone = null,
    ) {
        if ($recipientName === '' || mb_strlen($recipientName) > 200 || $addressLine1 === '' || mb_strlen($addressLine1) > 500) {
            throw new DomainException('Recipient and primary address are required.');
        }
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new DomainException('Address country code must use ISO alpha-2 uppercase format.');
        }
        foreach ([[$companyName, 255], [$taxCode, 64], [$addressLine2, 500], [$locality, 160], [$subdivision, 160], [$postalCode, 32], [$phone, 32]] as [$value, $limit]) {
            if (is_string($value) && mb_strlen($value) > $limit) {
                throw new DomainException('Address field exceeds its approved maximum length.');
            }
        }
    }

    /** @return array<string, string|null> */
    public function snapshot(): array
    {
        return [
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
        ];
    }
}
