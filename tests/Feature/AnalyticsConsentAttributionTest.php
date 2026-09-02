<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Growth\Application\RecordAnalyticsConsent;
use App\Modules\Growth\Application\ResolveAnalyticsConsentAttribution;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnalyticsConsentAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_granted_consent_records_minimized_first_and_last_touch_with_http_only_evidence(): void
    {
        $response = $this->postJson(route('analytics.consent.store'), [
            'decision' => 'granted',
            'operation_key' => 'analytics-consent-operation-001',
            'attribution' => [
                'source' => 'google',
                'medium' => 'cpc',
                'campaign' => 'ong-gio-2026',
                'landing_path' => '/san-pham/ong-gio-tron-inox',
                'referrer_host' => 'www.google.com',
            ],
        ])->assertCreated()->assertJsonPath('consent', 'granted')
            ->assertJsonPath('policy_revision', 'analytics-consent-v1');

        $cookie = collect($response->headers->getCookies())->first(
            static fn ($candidate): bool => $candidate->getName() === 'kaiyo_analytics_consent'
        );
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        $this->assertDatabaseCount('analytics_consents', 1);
        $this->assertDatabaseHas('analytics_attribution_touches', [
            'source' => 'google', 'medium' => 'cpc', 'campaign' => 'ong-gio-2026',
            'landing_path' => '/san-pham/ong-gio-tron-inox', 'referrer_host' => 'www.google.com',
        ]);

        $consentPublicId = (string) DB::table('analytics_consents')->value('public_id');
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)->postJson(route('analytics.attribution.store'), [
            'operation_key' => 'analytics-touch-operation-002',
            'source' => 'newsletter',
            'medium' => 'email',
            'campaign' => 'follow-up',
            'landing_path' => '/lien-he',
        ])->assertAccepted();

        $touches = DB::table('analytics_attribution_touches')->orderBy('id')->get();
        self::assertCount(2, $touches);
        self::assertSame('google', $touches[0]->source);
        self::assertSame('newsletter', $touches[1]->source);
        self::assertFalse(DB::getSchemaBuilder()->hasColumn('analytics_attribution_touches', 'ip'));
        self::assertFalse(DB::getSchemaBuilder()->hasColumn('analytics_attribution_touches', 'user_agent'));
    }

    public function test_denial_and_expiry_fail_closed_without_attribution_storage(): void
    {
        $response = $this->postJson(route('analytics.consent.store'), [
            'decision' => 'denied',
            'operation_key' => 'analytics-consent-denied-001',
        ])->assertCreated();
        $cookie = collect($response->headers->getCookies())->first(
            static fn ($candidate): bool => $candidate->getName() === 'kaiyo_analytics_consent'
        );
        self::assertNotNull($cookie);

        $consentPublicId = (string) DB::table('analytics_consents')->value('public_id');
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)->postJson(route('analytics.attribution.store'), [
            'operation_key' => 'analytics-touch-denied-001',
            'source' => 'google',
            'landing_path' => '/',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('analytics_attribution_touches', 0);

        DB::table('analytics_consents')->update(['decision' => 'granted', 'expires_at' => now()->subSecond()]);
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)->postJson(route('analytics.attribution.store'), [
            'operation_key' => 'analytics-touch-expired-001',
            'source' => 'google',
            'landing_path' => '/',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('analytics_attribution_touches', 0);
    }

    public function test_consent_and_touch_replays_are_idempotent_and_conflicts_fail_closed(): void
    {
        $recorder = app(RecordAnalyticsConsent::class);
        $first = $recorder->execute('stable-session-id', 'granted', 'analytics-consent-replay-001');
        $retry = $recorder->execute('stable-session-id', 'granted', 'analytics-consent-replay-001');
        self::assertSame($first->publicId, $retry->publicId);
        $this->assertDatabaseCount('analytics_consents', 1);

        try {
            $recorder->execute('stable-session-id', 'denied', 'analytics-consent-replay-001');
            self::fail('Conflicting consent replay must fail closed.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $touch = ['operation_key' => 'analytics-touch-replay-001', 'source' => 'direct', 'landing_path' => '/'];
        $consentPublicId = $first->publicId;
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)
            ->postJson(route('analytics.attribution.store'), $touch)->assertAccepted();
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)
            ->postJson(route('analytics.attribution.store'), $touch)->assertAccepted();
        $this->assertDatabaseCount('analytics_attribution_touches', 1);
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)
            ->postJson(route('analytics.attribution.store'), [...$touch, 'source' => 'tampered'])->assertUnprocessable();
        $this->assertDatabaseCount('analytics_attribution_touches', 1);
    }

    public function test_attribution_rejects_full_urls_query_strings_and_unbounded_touch_history(): void
    {
        config()->set('analytics.max_attribution_touches', 1);
        $response = $this->postJson(route('analytics.consent.store'), [
            'decision' => 'granted', 'operation_key' => 'analytics-consent-bounds-001',
        ])->assertCreated();
        $cookie = collect($response->headers->getCookies())->first(
            static fn ($candidate): bool => $candidate->getName() === 'kaiyo_analytics_consent'
        );
        self::assertNotNull($cookie);

        foreach (['https://example.test/path', '/path?email=person@example.test', '//evil.example/path'] as $index => $path) {
            $consentPublicId = (string) DB::table('analytics_consents')->value('public_id');
            $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)->postJson(route('analytics.attribution.store'), [
                'operation_key' => 'analytics-touch-invalid-'.$index,
                'source' => 'source',
                'landing_path' => $path,
            ])->assertUnprocessable();
        }
        $valid = ['operation_key' => 'analytics-touch-bounded-001', 'source' => 'direct', 'landing_path' => '/'];
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)
            ->postJson(route('analytics.attribution.store'), $valid)->assertAccepted();
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $consentPublicId)
            ->postJson(route('analytics.attribution.store'), [...$valid, 'operation_key' => 'analytics-touch-bounded-002'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('analytics_attribution_touches', 1);
    }

    public function test_new_decision_revokes_prior_session_consent_and_old_cookie_cannot_be_replayed(): void
    {
        $recorder = app(RecordAnalyticsConsent::class);
        $granted = $recorder->execute('revocation-session', 'granted', 'analytics-consent-granted-before-revoke');
        $denied = $recorder->execute('revocation-session', 'denied', 'analytics-consent-denied-after-grant');

        self::assertFalse(app(ResolveAnalyticsConsentAttribution::class)
            ->execute($granted->publicId, 'analytics-consent-v1')->granted);
        self::assertFalse(app(ResolveAnalyticsConsentAttribution::class)
            ->execute($denied->publicId, 'analytics-consent-v1')->granted);
        self::assertNotNull(DB::table('analytics_consents')->where('public_id', $granted->publicId)->value('revoked_at'));
        $this->withCredentials()->withCookie('kaiyo_analytics_consent', $granted->publicId)
            ->postJson(route('analytics.attribution.store'), [
                'operation_key' => 'analytics-touch-after-revocation',
                'source' => 'replay',
                'landing_path' => '/',
            ])->assertUnprocessable();
        $this->assertDatabaseCount('analytics_attribution_touches', 0);
    }
}
