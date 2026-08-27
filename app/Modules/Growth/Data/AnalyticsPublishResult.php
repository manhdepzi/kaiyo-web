<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

final readonly class AnalyticsPublishResult
{
    private function __construct(public bool $succeeded, public ?string $reference, public ?string $errorCode) {}

    public static function success(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function failure(string $errorCode): self
    {
        return new self(false, null, $errorCode);
    }
}
