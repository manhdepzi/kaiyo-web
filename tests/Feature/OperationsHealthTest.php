<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_summary_is_payload_safe_and_observational_by_default(): void
    {
        self::assertSame(0, Artisan::call('operations:health', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('"healthy":true', $output);
        self::assertStringContainsString('"readiness"', $output);
        self::assertStringNotContainsString('payload_hash', $output);
    }

    public function test_explicit_age_and_dead_gates_fail_without_revealing_record_identity(): void
    {
        $publicId = (string) Str::ulid();
        DB::table('dispatch_records')->insert([
            'public_id' => $publicId,
            'event_identity_hash' => hash('sha256', 'health-dead', true),
            'event_type' => 'catalog.projection.changed',
            'event_version' => 1,
            'aggregate_type' => 'product',
            'aggregate_public_id' => 'product-health',
            'payload' => '{}',
            'payload_hash' => hash('sha256', '{}', true),
            'state' => 'dead',
            'attempt_count' => 8,
            'available_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        self::assertSame(1, Artisan::call('operations:health', ['--fail-on-dead' => true, '--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('Dead outbox records are present.', $output);
        self::assertStringNotContainsString($publicId, $output);
    }

    public function test_invalid_gate_is_rejected_without_reading_operational_tables(): void
    {
        self::assertSame(2, Artisan::call('operations:health', ['--max-outbox-pending-age' => '-1']));
        self::assertStringContainsString('must be a non-negative integer', Artisan::output());
    }

    public function test_operational_reader_failure_is_sanitized_and_fails_closed(): void
    {
        DB::shouldReceive('table')->once()->andThrow(new \RuntimeException('database password should not appear'));

        self::assertSame(1, Artisan::call('operations:health', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('Operational data dependencies are unavailable.', $output);
        self::assertStringNotContainsString('database password should not appear', $output);
    }
}
