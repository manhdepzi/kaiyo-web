<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DesignTokenContrastTest extends TestCase
{
    #[DataProvider('contrastPairs')]
    public function test_required_text_pairs_meet_wcag_aa(string $foreground, string $background, float $minimum): void
    {
        self::assertGreaterThanOrEqual($minimum, $this->contrast($foreground, $background));
    }

    /** @return iterable<string, array{string, string, float}> */
    public static function contrastPairs(): iterable
    {
        yield 'light primary text' => ['#101828', '#ffffff', 4.5];
        yield 'light muted text' => ['#475467', '#ffffff', 4.5];
        yield 'light primary action' => ['#ffffff', '#075985', 4.5];
        yield 'light success message' => ['#14532d', '#dcfce7', 4.5];
        yield 'light warning message' => ['#78350f', '#fef3c7', 4.5];
        yield 'light danger message' => ['#7f1d1d', '#fee2e2', 4.5];
        yield 'dark primary text' => ['#f8fafc', '#0d1b2a', 4.5];
        yield 'dark muted text' => ['#bac6d6', '#0d1b2a', 4.5];
        yield 'dark primary action' => ['#082f49', '#22d3ee', 4.5];
    }

    private function contrast(string $foreground, string $background): float
    {
        $lighter = max($this->luminance($foreground), $this->luminance($background));
        $darker = min($this->luminance($foreground), $this->luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        $values = array_map(
            static fn (string $channel): float => hexdec($channel) / 255,
            str_split(ltrim($hex, '#'), 2),
        );
        $linear = array_map(
            static fn (float $value): float => $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4,
            $values,
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
