<?php

declare(strict_types=1);

namespace App\Modules\CRM\Support;

use DomainException;
use Illuminate\Support\Str;

final class CrmIdentityNormalizer
{
    public function email(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false || mb_strlen($normalized) > 320) {
            throw new DomainException('Email identity is invalid.');
        }

        return $normalized;
    }

    public function phone(string $value): string
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim($value)) ?? '';
        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $normalized) !== 1) {
            throw new DomainException('Phone identity must use an explicit E.164 country code.');
        }

        return $normalized;
    }

    public function taxCode(string $value): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value), 'UTF-8')) ?? '';
        if ($normalized === '' || strlen($normalized) > 64) {
            throw new DomainException('Tax identity is invalid.');
        }

        return $normalized;
    }

    public function name(string $value): string
    {
        $normalized = mb_strtolower(Str::ascii(trim($value)), 'UTF-8');
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
        if ($normalized === '' || mb_strlen($normalized) > 200) {
            throw new DomainException('CRM name is invalid.');
        }

        return $normalized;
    }

    public function hash(string $type, string $normalized): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? '' : $decoded;
        }
        if ($key === '') {
            throw new DomainException('The application identity key is unavailable.');
        }

        return hash_hmac('sha256', $type."\0".$normalized, $key, true);
    }
}
