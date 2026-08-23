<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain;

final readonly class ScanResult
{
    private function __construct(public bool $clean, public string $code) {}

    public static function clean(): self
    {
        return new self(true, 'clean');
    }

    public static function rejected(string $code): self
    {
        return new self(false, $code);
    }
}
