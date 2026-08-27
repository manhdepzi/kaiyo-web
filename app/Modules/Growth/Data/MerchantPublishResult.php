<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

final readonly class MerchantPublishResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $destinationReference,
        public ?string $errorCode,
    ) {}

    public static function success(string $destinationReference): self
    {
        return new self(true, $destinationReference, null);
    }

    public static function failure(string $errorCode): self
    {
        return new self(false, null, $errorCode);
    }
}
