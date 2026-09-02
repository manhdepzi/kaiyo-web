<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Growth\Application\ReadGrowthDeliveryStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GrowthDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_reports_bounded_counts_ages_and_error_concentration_without_identities(): void
    {
        $merchantId = (string) Str::ulid();
        $this->merchant('pending', now()->subSeconds(125), 'provider_unconfigured', $merchantId);
        $this->merchant('dead', now()->subSeconds(60), 'provider_unconfigured');
        $analyticsId = (string) Str::ulid();
        $this->analytics('processing', now()->subSeconds(95), 'destination_failure', $analyticsId);
        $this->analytics('completed', now()->subSeconds(30));

        $status = app(ReadGrowthDeliveryStatus::class)->execute();
        self::assertSame(['pending' => 1, 'processing' => 0, 'completed' => 0, 'dead' => 1], $status['merchant']->counts);
        self::assertGreaterThanOrEqual(125, $status['merchant']->oldestPendingAgeSeconds);
        self::assertSame([['code' => 'provider_unconfigured', 'count' => 2]], $status['merchant']->errors);
        self::assertSame(['pending' => 0, 'processing' => 1, 'completed' => 1, 'dead' => 0], $status['analytics']->counts);
        self::assertGreaterThanOrEqual(95, $status['analytics']->oldestProcessingAgeSeconds);

        self::assertSame(0, Artisan::call('growth:delivery-status', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('provider_unconfigured', $output);
        self::assertStringNotContainsString($merchantId, $output);
        self::assertStringNotContainsString($analyticsId, $output);
    }

    public function test_monitoring_gates_fail_for_old_backlog_stale_processing_and_dead_intents(): void
    {
        $this->merchant('pending', now()->subSeconds(61));
        $this->analytics('processing', now()->subSeconds(31));
        $this->analytics('dead', now()->subSecond(), 'provider_unconfigured');

        self::assertSame(1, Artisan::call('growth:delivery-status', [
            '--max-pending-age' => '60',
            '--max-processing-age' => '30',
            '--fail-on-dead' => true,
        ]));
        $output = Artisan::output();
        self::assertStringContainsString('merchant pending intent exceeds', $output);
        self::assertStringContainsString('analytics processing lease exceeds', $output);
        self::assertStringContainsString('analytics dead intents are present', $output);
    }

    public function test_invalid_threshold_is_rejected_without_query_side_effects(): void
    {
        self::assertSame(2, Artisan::call('growth:delivery-status', ['--max-pending-age' => '-1']));
        self::assertStringContainsString('must be a non-negative integer', Artisan::output());
    }

    private function merchant(string $state, mixed $time, ?string $error = null, ?string $publicId = null): void
    {
        DB::table('merchant_feed_refresh_requests')->insert([
            'public_id' => $publicId ?? (string) Str::ulid(),
            'business_fact_public_id' => (string) Str::ulid(),
            'event_type' => 'catalog.projection.changed',
            'scope_type' => 'variant',
            'scope_public_id' => (string) Str::ulid(),
            'state' => $state,
            'attempt_count' => $state === 'pending' ? 1 : 5,
            'available_at' => $time,
            'last_error_code' => $error,
            'last_attempted_at' => $state === 'processing' ? $time : null,
            'completed_at' => in_array($state, ['completed', 'dead'], true) ? $time : null,
            'created_at' => $time,
            'updated_at' => $time,
        ]);
    }

    private function analytics(string $state, mixed $time, ?string $error = null, ?string $publicId = null): void
    {
        DB::table('analytics_event_intents')->insert([
            'public_id' => $publicId ?? (string) Str::ulid(),
            'producer_identity_hash' => hash('sha256', (string) Str::ulid(), true),
            'event_identity' => 'order-placed:'.Str::ulid(),
            'event_type' => 'order.placed',
            'subject_type' => 'order',
            'subject_public_id' => (string) Str::ulid(),
            'attributes' => '{}',
            'occurred_at' => $time,
            'state' => $state,
            'attempt_count' => $state === 'pending' ? 1 : 5,
            'available_at' => $time,
            'last_attempted_at' => $state === 'processing' ? $time : null,
            'last_error_code' => $error,
            'completed_at' => in_array($state, ['completed', 'dead'], true) ? $time : null,
            'created_at' => $time,
            'updated_at' => $time,
        ]);
    }
}
