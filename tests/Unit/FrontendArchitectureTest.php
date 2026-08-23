<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FrontendArchitectureTest extends TestCase
{
    public function test_step_27_records_stack_ownership_surfaces_and_quality_gates(): void
    {
        $architecture = $this->read('docs/frontend/frontend-architecture.md');
        $matrix = $this->read('docs/frontend/frontend-state-matrix.md');

        foreach (['Blade SSR', 'Livewire', 'Alpine', 'one writable owner', 'Public', 'Customer', 'Sales', 'Admin', 'WCAG 2.2 AA', 'LCP ≤2.5 s', 'INP ≤200 ms', 'CLS ≤0.1'] as $required) {
            self::assertStringContainsStringIgnoringCase($required, $architecture);
        }
        foreach (['Loading', 'Empty', 'Validation', 'Conflict/stale', 'Permission-safe missing', 'Unknown write outcome', 'Quote request/access', 'Orders/detail/tracking', 'Import/Export/jobs'] as $required) {
            self::assertStringContainsString($required, $matrix);
        }
        self::assertStringContainsString('never query or mutate Eloquent', $architecture);
        self::assertStringContainsStringIgnoringCase('no global SPA/store package', $architecture);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
        self::assertIsString($contents);

        return $contents;
    }
}
