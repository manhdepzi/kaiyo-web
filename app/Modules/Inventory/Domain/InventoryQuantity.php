<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use DomainException;

final readonly class InventoryQuantity
{
    private const FACTOR = 10_000;

    private function __construct(public int $units) {}

    public static function fromUnits(int $units): self
    {
        return new self($units);
    }

    public static function from(string|int $value): self
    {
        $text = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d{1,4})?$/', $text)) {
            throw new DomainException('Inventory quantity must have at most four decimal places.');
        }
        $negative = str_starts_with($text, '-');
        $unsigned = ltrim($text, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $units = ((int) $whole * self::FACTOR) + (int) str_pad($fraction, 4, '0');

        return new self($negative ? -$units : $units);
    }

    public function decimal(): string
    {
        $absolute = abs($this->units);
        $value = intdiv($absolute, self::FACTOR).'.'.str_pad((string) ($absolute % self::FACTOR), 4, '0', STR_PAD_LEFT);

        return $this->units < 0 ? '-'.$value : $value;
    }
}
