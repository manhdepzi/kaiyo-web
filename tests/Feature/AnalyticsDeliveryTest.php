<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Growth\Application\DeliverAnalyticsBatch;
use App\Modules\Growth\Application\RecordAnalyticsAttributionTouch;
use App\Modules\Growth\Application\RecordAnalyticsConsent;
use App\Modules\Growth\Contracts\AnalyticsDestination;
use App\Modules\Growth\Data\AnalyticsAttributionTouch;
use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Growth\Data\AnalyticsPublishResult;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnalyticsDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_consent_is_fail_closed_and_destination_event_identity_is_deduplicated(): void
    {
        $destination = new class implements AnalyticsDestination
        {
            /** @var list<string> */
            public array $calls = [];

            public function publish(AnalyticsEvent $event, string $idempotencyKey): AnalyticsPublishResult
            {
                $this->calls[] = $event->identity.'|'.$idempotencyKey;

                return AnalyticsPublishResult::success('analytics-'.$event->identity);
            }
        };
        $this->app->instance(AnalyticsDestination::class, $destination);
        $events = [
            $this->event('event-order-001', 'order.placed', true, ['currency' => 'VND', 'value' => 120000]),
            $this->event('event-view-001', 'catalog.product_viewed', false, ['catalog_revision' => 'catalog-v1']),
        ];

        $batch = app(DeliverAnalyticsBatch::class)->execute('ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-operation-001', $events);
        self::assertSame('completed', $batch->state);
        self::assertSame([2, 1, 1, 0], [$batch->total_count, $batch->succeeded_count, $batch->suppressed_count, $batch->failed_count]);
        self::assertCount(1, $destination->calls);
        self::assertSame('consent_denied', DB::table('analytics_delivery_items')->where('outcome', 'suppressed')->value('error_code'));

        $retry = app(DeliverAnalyticsBatch::class)->execute('ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-operation-001', $events);
        self::assertSame($batch->getKey(), $retry->getKey());
        self::assertCount(1, $destination->calls);

        $duplicateBatch = app(DeliverAnalyticsBatch::class)->execute('ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-operation-002', $events);
        self::assertSame('completed', $duplicateBatch->state);
        self::assertSame(0, $duplicateBatch->total_count);
        self::assertSame(2, DB::table('analytics_delivery_items')->count());
    }

    public function test_provider_failure_is_visible_and_retry_uses_the_same_item(): void
    {
        $events = [$this->event('event-quote-001', 'quotation.requested', true, ['currency' => 'VND'])];
        $failed = app(DeliverAnalyticsBatch::class)->execute('ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-operation-003', $events);
        self::assertSame('failed', $failed->state);
        self::assertSame('provider_unconfigured', DB::table('analytics_delivery_items')->value('error_code'));

        $this->app->instance(AnalyticsDestination::class, new class implements AnalyticsDestination
        {
            public function publish(AnalyticsEvent $event, string $idempotencyKey): AnalyticsPublishResult
            {
                return AnalyticsPublishResult::success('retry-success');
            }
        });
        $completed = app(DeliverAnalyticsBatch::class)->execute('ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-operation-003', $events);
        self::assertSame('completed', $completed->state);
        self::assertSame(2, DB::table('analytics_delivery_items')->value('attempt_count'));
        self::assertSame('retry-success', DB::table('analytics_delivery_items')->value('destination_reference'));
    }

    public function test_event_catalog_rejects_unknown_types_and_raw_personal_data(): void
    {
        foreach ([
            $this->event('event-invalid-001', 'invented.event', true),
            $this->event('event-invalid-002', 'crm.lead_created', true, ['email' => 'person@example.test']),
        ] as $index => $event) {
            try {
                app(DeliverAnalyticsBatch::class)->execute('ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-invalid-'.$index, [$event]);
                self::fail('Unknown events and raw PII must fail closed.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
        self::assertSame(0, DB::table('analytics_delivery_batches')->count());
    }

    public function test_admin_monitor_is_private_permission_and_two_factor_gated(): void
    {
        app(DeliverAnalyticsBatch::class)->execute(
            'ga4',
            'analytics-v1',
            'analytics-consent-v1',
            'analytics-operation-admin-001',
            [$this->event('event-admin-001', 'order.placed', false, ['currency' => 'VND'])],
        );

        $this->actingAs(UserAccount::factory()->create())->get(route('admin.analytics'))->assertForbidden();

        $actor = $this->analyticsActor();
        $this->actingAs($actor)->get(route('admin.analytics'))->assertRedirect(route('account.security'));
        $actor->forceFill([
            'two_factor_secret' => encrypt('analytics-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['analytics-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_at' => now(),
        ])->save();

        $this->actingAs($actor)->get(route('admin.analytics', ['state' => 'completed', 'destination' => 'ga4']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Analytics delivery')
            ->assertSee('analytics-v1')
            ->assertSee('Suppressed')
            ->assertSee('Analytics');
    }

    public function test_delivery_requires_server_consent_evidence_and_rebuilds_first_last_attribution(): void
    {
        $firstTouch = new AnalyticsAttributionTouch('google', 'cpc', 'spring', null, null, '/san-pham/a', 'www.google.com');
        $consent = app(RecordAnalyticsConsent::class)->execute(
            'analytics-evidence-session', 'granted', 'analytics-evidence-consent-001', $firstTouch,
        );
        app(RecordAnalyticsAttributionTouch::class)->execute(
            $consent->publicId,
            'analytics-evidence-touch-002',
            new AnalyticsAttributionTouch('newsletter', 'email', 'follow-up', null, null, '/lien-he', null),
        );
        $destination = new class implements AnalyticsDestination
        {
            /** @var list<AnalyticsEvent> */
            public array $events = [];

            public function publish(AnalyticsEvent $event, string $idempotencyKey): AnalyticsPublishResult
            {
                $this->events[] = $event;

                return AnalyticsPublishResult::success('analytics-evidence-success');
            }
        };
        $this->app->instance(AnalyticsDestination::class, $destination);
        $event = new AnalyticsEvent(
            'event-evidence-001', 'contact.clicked', 'contact', null,
            new DateTimeImmutable('2026-08-31T09:00:00+07:00'), true,
            ['channel' => 'phone'], $consent->publicId,
        );
        app(DeliverAnalyticsBatch::class)->execute(
            'ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-evidence-batch-001', [$event],
        );

        self::assertCount(1, $destination->events);
        self::assertSame('google', $destination->events[0]->attributes['attribution_first_source']);
        self::assertSame('newsletter', $destination->events[0]->attributes['attribution_last_source']);
        self::assertSame('/san-pham/a', $destination->events[0]->attributes['attribution_first_landing_path']);
        self::assertSame('/lien-he', $destination->events[0]->attributes['attribution_last_landing_path']);

        $spoofed = new AnalyticsEvent(
            'event-evidence-spoofed', 'contact.clicked', 'contact', null,
            new DateTimeImmutable('2026-08-31T09:01:00+07:00'), true, ['channel' => 'phone'], null,
        );
        $batch = app(DeliverAnalyticsBatch::class)->execute(
            'ga4', 'analytics-v1', 'analytics-consent-v1', 'analytics-evidence-batch-002', [$spoofed],
        );
        self::assertSame(1, $batch->suppressed_count);
        self::assertCount(1, $destination->events, 'Caller-provided consent boolean must not bypass server evidence.');
    }

    /** @param array<string, bool|float|int|string|null> $attributes */
    private function event(string $identity, string $type, bool $consent, array $attributes = []): AnalyticsEvent
    {
        $evidence = $consent
            ? app(RecordAnalyticsConsent::class)->execute('session-'.$identity, 'granted', 'consent-'.$identity)->publicId
            : null;

        return new AnalyticsEvent($identity, $type, 'commerce', '01KAIYOANALYTICSSUBJECT01', new DateTimeImmutable('2026-08-26T09:00:00+07:00'), $consent, $attributes, $evidence);
    }

    private function analyticsActor(): UserAccount
    {
        $actor = UserAccount::factory()->create();
        $permission = PermissionDefinition::query()->where('code', 'analytics.read')->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module('analytics')->persistenceValues(),
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(),
            'reason' => 'Analytics monitor test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);

        return $actor;
    }
}
