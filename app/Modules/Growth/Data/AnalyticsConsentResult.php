<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

use DateTimeImmutable;

final readonly class AnalyticsConsentResult
{
    public function __construct(
        public string $publicId,
        public string $decision,
        public string $policyRevision,
        public DateTimeImmutable $expiresAt,
    ) {}
}
