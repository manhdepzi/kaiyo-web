<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Foundation\Application\ReadPerformanceBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class PerformanceBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_reports_only_probe_metadata_and_default_mode_is_observational(): void
    {
        self::assertSame(0, Artisan::call('performance:baseline', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('database.select_one', $output);
        self::assertStringContainsString('outbox.status', $output);
        self::assertStringContainsString('growth.delivery_status', $output);
        self::assertStringNotContainsString('payload', $output);

        $probes = app(ReadPerformanceBaseline::class)->execute();
        self::assertCount(4, $probes);
        self::assertSame('database.select_one', $probes[0]->name);
        self::assertSame('ok', $probes[0]->status);
    }

    public function test_invalid_latency_gate_is_rejected_before_any_probe_is_run(): void
    {
        self::assertSame(2, Artisan::call('performance:baseline', ['--max-ms' => '-1']));
        self::assertStringContainsString('must be a non-negative integer', Artisan::output());
    }
}
