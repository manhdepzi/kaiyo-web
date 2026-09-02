<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class PerformanceProbe
{
    public function __construct(
        public string $name,
        public string $status,
        public float $durationMilliseconds,
    ) {}

    /** @return array{name:string,status:string,duration_ms:float} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'duration_ms' => $this->durationMilliseconds,
        ];
    }
}
