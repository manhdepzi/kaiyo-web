<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

use DomainException;

final readonly class MerchantRefreshRequestData
{
    public function __construct(
        public int $id,
        public string $scopeType,
        public string $scopePublicId,
        public int $attemptCount,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        if (! isset($row['id'], $row['scope_type'], $row['scope_public_id'], $row['attempt_count'])
            || ! is_numeric($row['id']) || ! is_string($row['scope_type'])
            || ! is_string($row['scope_public_id']) || ! is_numeric($row['attempt_count'])) {
            throw new DomainException('Merchant refresh request persistence is invalid.');
        }

        return new self((int) $row['id'], $row['scope_type'], $row['scope_public_id'], (int) $row['attempt_count']);
    }
}
