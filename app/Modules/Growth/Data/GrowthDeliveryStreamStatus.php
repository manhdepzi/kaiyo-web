<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

final readonly class GrowthDeliveryStreamStatus
{
    /**
     * @param  array{pending:int, processing:int, completed:int, dead:int}  $counts
     * @param  list<array{code:string,count:int}>  $errors
     */
    public function __construct(
        public string $stream,
        public array $counts,
        public ?int $oldestPendingAgeSeconds,
        public ?int $oldestProcessingAgeSeconds,
        public array $errors,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stream' => $this->stream,
            'counts' => $this->counts,
            'oldest_pending_age_seconds' => $this->oldestPendingAgeSeconds,
            'oldest_processing_age_seconds' => $this->oldestProcessingAgeSeconds,
            'error_codes' => $this->errors,
        ];
    }
}
