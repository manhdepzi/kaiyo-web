<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

final readonly class AnalyticsAttributionTouch
{
    public function __construct(
        public ?string $source,
        public ?string $medium,
        public ?string $campaign,
        public ?string $term,
        public ?string $content,
        public string $landingPath,
        public ?string $referrerHost,
    ) {}

    /** @return array<string, string|null> */
    public function values(): array
    {
        return [
            'source' => $this->source,
            'medium' => $this->medium,
            'campaign' => $this->campaign,
            'term' => $this->term,
            'content' => $this->content,
            'landing_path' => $this->landingPath,
            'referrer_host' => $this->referrerHost,
        ];
    }
}
