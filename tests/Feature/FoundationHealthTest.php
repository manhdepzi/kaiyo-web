<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FoundationHealthTest extends TestCase
{
    public function test_liveness_is_available_without_configuration_details(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader(AssignCorrelationId::HEADER)
            ->assertSee('Application up');
    }

    public function test_readiness_is_sanitized_and_ready_when_optional_checks_are_disabled(): void
    {
        $this->get('/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'checks' => ['application' => 'ok'],
            ]);
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
