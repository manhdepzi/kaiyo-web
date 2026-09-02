<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

use DomainException;

final readonly class MerchantProjectionSource
{
    public function __construct(
        public int $id,
        public string $variantPublicId,
        public string $sku,
        public string $variantStatus,
        public int $variantVersion,
        public ?string $variantDeletedAt,
        public string $productPublicId,
        public string $name,
        public string $slug,
        public string $productStatus,
        public int $productVersion,
        public ?string $productDeletedAt,
        public string $categoryStatus,
        public int $categoryVersion,
        public ?string $categoryDeletedAt,
        public ?string $brandStatus,
        public ?int $brandVersion,
        public ?string $brandDeletedAt,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        foreach (['id', 'variant_public_id', 'sku', 'variant_status', 'variant_version', 'product_public_id', 'name',
            'slug', 'product_status', 'product_version', 'category_status', 'category_version'] as $key) {
            if (! array_key_exists($key, $row)) {
                throw new DomainException('Merchant projection source persistence is invalid.');
            }
        }

        return new self(
            (int) $row['id'], (string) $row['variant_public_id'], (string) $row['sku'], (string) $row['variant_status'],
            (int) $row['variant_version'], self::nullableString($row['variant_deleted_at'] ?? null),
            (string) $row['product_public_id'], (string) $row['name'], (string) $row['slug'], (string) $row['product_status'],
            (int) $row['product_version'], self::nullableString($row['product_deleted_at'] ?? null),
            (string) $row['category_status'], (int) $row['category_version'], self::nullableString($row['category_deleted_at'] ?? null),
            self::nullableString($row['brand_status'] ?? null), isset($row['brand_version']) ? (int) $row['brand_version'] : null,
            self::nullableString($row['brand_deleted_at'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
