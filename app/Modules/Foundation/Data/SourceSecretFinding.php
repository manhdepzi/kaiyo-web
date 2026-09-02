<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Data;

final readonly class SourceSecretFinding
{
    public function __construct(
        public string $path,
        public int $line,
        public string $category,
    ) {}

    /** @return array{path:string,line:int,category:string} */
    public function toArray(): array
    {
        return ['path' => $this->path, 'line' => $this->line, 'category' => $this->category];
    }
}
