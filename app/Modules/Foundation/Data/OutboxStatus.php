<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class OutboxStatus
{
    /**
     * @param  array{pending: int, publishing: int, published: int, dead: int}  $counts
     */
    public function __construct(
        public array $counts,
        public ?int $oldestPendingAgeSeconds,
        public ?int $oldestPublishingAgeSeconds,
    ) {}

    /**
     * @return array{
     *     counts: array{pending: int, publishing: int, published: int, dead: int},
     *     oldest_pending_age_seconds: ?int,
     *     oldest_publishing_age_seconds: ?int
     * }
     */
    public function toArray(): array
    {
        return [
            'counts' => $this->counts,
            'oldest_pending_age_seconds' => $this->oldestPendingAgeSeconds,
            'oldest_publishing_age_seconds' => $this->oldestPublishingAgeSeconds,
        ];
    }
}
