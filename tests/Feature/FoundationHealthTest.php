<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class FoundationHealthTest extends TestCase
{
    public function test_liveness_is_available_without_configuration_details(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader(AssignCorrelationId::HEADER)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertSee('Application up');
    }

    public function test_readiness_is_sanitized_and_ready_when_optional_checks_are_disabled(): void
    {
        config()->set('health.check_database', false);
        config()->set('health.check_cache', false);

        $response = $this->get('/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'checks' => ['application' => 'ok'],
            ]);

        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_readiness_checks_enabled_dependencies_without_starting_a_session(): void
    {
        config()->set('health.check_database', true);
        config()->set('health.check_cache', true);

        $response = $this->get('/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'checks' => [
                    'application' => 'ok',
                    'database' => 'ok',
                    'cache' => 'ok',
                ],
            ]);

        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_readiness_dependency_failure_is_sanitized_and_returns_service_unavailable(): void
    {
        config()->set('health.check_database', false);
        config()->set('health.check_cache', true);
        $cache = $this->mock(CacheManager::class);
        $cache->shouldReceive('store')->once()->andThrow(new RuntimeException('sensitive dependency detail'));

        $response = $this->get('/ready')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
                'checks' => [
                    'application' => 'ok',
                    'dependencies' => 'failed',
                ],
            ])
            ->assertDontSee('sensitive dependency detail');

        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_valid_correlation_id_is_preserved(): void
    {
        $correlationId = (string) Str::uuid();

        $this->withHeader(AssignCorrelationId::HEADER, $correlationId)
            ->get('/ready')
            ->assertHeader(AssignCorrelationId::HEADER, $correlationId);
    }

    public function test_untrusted_correlation_id_is_replaced(): void
    {
        $response = $this->withHeader(AssignCorrelationId::HEADER, "invalid\r\nvalue")
            ->get('/ready');

        $actual = (string) $response->headers->get(AssignCorrelationId::HEADER);

        self::assertTrue(Str::isUuid($actual));
    }
}
