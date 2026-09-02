<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

final readonly class ResolvedAnalyticsConsent
{
    /** @param array<string, string> $attribution */
    public function __construct(
        public bool $granted,
        public array $attribution = [],
    ) {}
}
