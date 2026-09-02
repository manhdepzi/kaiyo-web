<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Growth\Application\ProcessAnalyticsIntents;
use App\Modules\Growth\Application\RecordAnalyticsConsent;
use App\Modules\Growth\Application\StoreAnalyticsIntent;
use App\Modules\Growth\Contracts\AnalyticsDestination;
use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Growth\Data\AnalyticsPublishResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnalyticsIntentProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_intent_is_deduplicated_and_missing_consent_is_suppressed_without_provider_call(): void
    {
        $destination = new class implements AnalyticsDestination
        {
            public int $calls = 0;

            public function publish(AnalyticsEvent $event, string $idempotencyKey): AnalyticsPublishResult
            {
                $this->calls++;

                return AnalyticsPublishResult::success('unexpected');
            }
        };
        $this->app->instance(AnalyticsDestination::class, $destination);
        $event = $this->event(null);
        app(StoreAnalyticsIntent::class)->record('producer:order:01TESTIDENTITY', $event);
        app(StoreAnalyticsIntent::class)->record('producer:order:01TESTIDENTITY', $event);

        $this->assertDatabaseCount('analytics_event_intents', 1);
        self::assertSame(1, app(ProcessAnalyticsIntents::class)->execute());
        self::assertSame(0, $destination->calls);
        $this->assertDatabaseHas('analytics_event_intents', ['state' => 'completed', 'attempt_count' => 1]);
        $this->assertDatabaseHas('analytics_delivery_items', ['outcome' => 'suppressed', 'error_code' => 'consent_denied']);
    }

    public function test_granted_consent_is_resolved_at_processing_time_and_destination_failure_retries(): void
    {
        $consent = app(RecordAnalyticsConsent::class)
            ->execute('analytics-intent-session', 'granted', 'analytics-intent-consent-operation');
        app(StoreAnalyticsIntent::class)->record('producer:order:02TESTIDENTITY', $this->event($consent->publicId));

        self::assertSame(1, app(ProcessAnalyticsIntents::class)->execute());
        $this->assertDatabaseHas('analytics_event_intents', [
            'state' => 'pending', 'attempt_count' => 1, 'last_error_code' => 'destination_failure',
        ]);
        $this->assertDatabaseHas('analytics_delivery_items', ['outcome' => 'failed', 'error_code' => 'provider_unconfigured']);

        DB::table('analytics_event_intents')->update(['available_at' => now()->subSecond()]);
        $this->app->instance(AnalyticsDestination::class, new class implements AnalyticsDestination
        {
            public function publish(AnalyticsEvent $event, string $idempotencyKey): AnalyticsPublishResult
            {
                return AnalyticsPublishResult::success('analytics-test-reference');
            }
        });
        self::assertSame(1, app(ProcessAnalyticsIntents::class)->execute());
        $this->assertDatabaseHas('analytics_event_intents', ['state' => 'completed', 'attempt_count' => 2]);
        $this->assertDatabaseHas('analytics_delivery_items', ['outcome' => 'succeeded', 'destination_reference' => 'analytics-test-reference']);
    }

    public function test_business_transaction_rolls_back_its_analytics_intent(): void
    {
        try {
            DB::transaction(function (): void {
                app(StoreAnalyticsIntent::class)->record('producer:rollback:03TESTIDENTITY', $this->event(null));
                throw new \RuntimeException('rollback probe');
            });
        } catch (\RuntimeException) {
            // Expected probe failure.
        }

        $this->assertDatabaseCount('analytics_event_intents', 0);
    }

    private function event(?string $consentPublicId): AnalyticsEvent
    {
        return new AnalyticsEvent(
            'order-placed:01TESTANALYTICSINTENT',
            'order.placed',
            'order',
            '01K5M6N7P8Q9R0S1T2V3W4X5Y6',
            now()->toDateTimeImmutable(),
            true,
            ['currency' => 'VND', 'source' => 'checkout', 'value_minor' => 100000],
            $consentPublicId,
        );
    }
}
