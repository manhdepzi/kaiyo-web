<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Support;

trait NormalizesEmail
{
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }
}
