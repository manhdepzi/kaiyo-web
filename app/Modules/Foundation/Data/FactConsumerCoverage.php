<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class FactConsumerCoverage
{
    /** @param list<string> $consumers */
    public function __construct(
        public string $factType,
        public string $owner,
        public array $consumers,
    ) {}

    /** @return array{fact_type:string,owner:string,consumers:list<string>,covered:bool} */
    public function toArray(): array
    {
        return [
            'fact_type' => $this->factType,
            'owner' => $this->owner,
            'consumers' => $this->consumers,
            'covered' => $this->consumers !== [],
        ];
    }
}
