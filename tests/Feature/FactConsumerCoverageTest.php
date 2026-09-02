<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Foundation\Application\DispatchFactCatalog;
use App\Modules\Foundation\Application\ReadFactConsumerCoverage;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class FactConsumerCoverageTest extends TestCase
{
    public function test_every_approved_fact_has_exactly_one_owner_and_declared_current_consumers(): void
    {
        $coverage = app(ReadFactConsumerCoverage::class)->execute();
        $known = app(DispatchFactCatalog::class)->factTypes();

        self::assertSame($known, array_map(static fn ($fact): string => $fact->factType, $coverage));
        foreach ($coverage as $fact) {
            self::assertNotSame('', $fact->owner);
            self::assertSame(array_values(array_unique($fact->consumers)), $fact->consumers);
        }
        self::assertSame(['commerce.order.placed'], array_map(
            static fn ($fact): string => $fact->factType,
            array_values(array_filter($coverage, static fn ($fact): bool => $fact->consumers === [])),
        ));
    }

    public function test_command_is_safe_for_automation_and_can_fail_for_open_coverage(): void
    {
        self::assertSame(0, Artisan::call('outbox:consumer-coverage', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('commerce.order.placed', $output);
        self::assertStringContainsString('notification.order.in_app', $output);
        self::assertStringNotContainsString('payload', $output);

        self::assertSame(1, Artisan::call('outbox:consumer-coverage', ['--require-all-covered' => true]));
        self::assertStringContainsString('No current consumer declared for commerce.order.placed', Artisan::output());
    }
}
