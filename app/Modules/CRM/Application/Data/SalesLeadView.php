<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class SalesLeadView
{
    public function __construct(
        public string $publicId,
        public string $displayName,
        public ?string $companyName,
        public ?string $email,
        public ?string $phone,
        public ?string $taxCode,
        public string $source,
        public string $status,
        public int $version,
        public ?string $convertedCustomerPublicId,
        public ?string $convertedCompanyPublicId,
        public bool $canUpdate,
        public bool $canConvert,
        public ?string $inquiryTopic = null,
        public ?string $inquiryMessage = null,
        public ?string $inquirySubmittedAt = null,
    ) {}
}
