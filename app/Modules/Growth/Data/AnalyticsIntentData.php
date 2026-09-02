<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

use DateTimeImmutable;
use DomainException;

final readonly class AnalyticsIntentData
{
    /** @param array<string, bool|float|int|string|null> $attributes */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $eventIdentity,
        public string $eventType,
        public string $subjectType,
        public ?string $subjectPublicId,
        public ?string $consentEvidencePublicId,
        public array $attributes,
        public DateTimeImmutable $occurredAt,
        public int $attemptCount,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromDatabaseRow(array $row): self
    {
        $attributes = json_decode(is_string($row['attributes'] ?? null) ? $row['attributes'] : '', true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($attributes) || ! is_int($row['id'] ?? null) || ! is_int($row['attempt_count'] ?? null)) {
            throw new DomainException('Stored analytics intent is invalid.');
        }
        foreach (['public_id', 'event_identity', 'event_type', 'subject_type', 'occurred_at'] as $key) {
            if (! is_string($row[$key] ?? null)) {
                throw new DomainException('Stored analytics intent is invalid.');
            }
        }
        $subjectPublicId = $row['subject_public_id'] ?? null;
        $consentPublicId = $row['consent_evidence_public_id'] ?? null;
        if (($subjectPublicId !== null && ! is_string($subjectPublicId)) || ($consentPublicId !== null && ! is_string($consentPublicId))) {
            throw new DomainException('Stored analytics intent is invalid.');
        }

        return new self(
            $row['id'], $row['public_id'], $row['event_identity'], $row['event_type'], $row['subject_type'],
            $subjectPublicId, $consentPublicId, $attributes, new DateTimeImmutable($row['occurred_at']), $row['attempt_count'],
        );
    }
}
