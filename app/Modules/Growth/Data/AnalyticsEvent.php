<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

use DateTimeImmutable;

final readonly class AnalyticsEvent
{
    /** @param array<string, bool|float|int|string|null> $attributes */
    public function __construct(
        public string $identity,
        public string $type,
        public string $subjectType,
        public ?string $subjectPublicId,
        public DateTimeImmutable $occurredAt,
        public bool $consentGranted,
        public array $attributes = [],
    ) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'event_type' => $this->type,
            'subject_type' => $this->subjectType,
            'subject_public_id' => $this->subjectPublicId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'attributes' => $this->attributes,
        ];
    }
}
