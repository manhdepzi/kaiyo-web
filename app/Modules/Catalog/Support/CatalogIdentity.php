<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use DomainException;
use Illuminate\Support\Str;

final class CatalogIdentity
{
    public function slug(string $value): string
    {
        $slug = Str::slug(trim($value));
        if ($slug === '' || strlen($slug) > 255) {
            throw new DomainException('Catalog slug is invalid.');
        }

        return $slug;
    }

    public function sku(string $value): string
    {
        $sku = mb_strtoupper(trim($value), 'UTF-8');
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{0,99}$/', $sku) !== 1) {
            throw new DomainException('SKU is invalid.');
        }

        return $sku;
    }
}
