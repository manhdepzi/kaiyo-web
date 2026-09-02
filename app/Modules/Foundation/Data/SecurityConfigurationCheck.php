<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class SecurityConfigurationCheck
{
    public function __construct(
        public string $name,
        public bool $passed,
    ) {}

    /** @return array{name:string,passed:bool} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'passed' => $this->passed];
    }
}
