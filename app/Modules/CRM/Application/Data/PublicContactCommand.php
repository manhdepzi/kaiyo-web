<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class PublicContactCommand
{
    public function __construct(
        public string $name,
        public ?string $companyName,
        public ?string $email,
        public ?string $phone,
        public string $topic,
        public string $message,
        public string $operationKey,
        public string $abuseKey,
        public ?string $analyticsConsentPublicId = null,
    ) {}
}
