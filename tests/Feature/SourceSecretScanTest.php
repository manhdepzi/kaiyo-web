<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Foundation\Application\ScanSourceSecrets;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SourceSecretScanTest extends TestCase
{
    public function test_scanner_reports_category_and_location_without_returning_secret_content(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kaiyo-secret-scan-');
        self::assertNotFalse($path);
        file_put_contents($path, 'token = "sk-123456789012345678901234";');

        try {
            $findings = app(ScanSourceSecrets::class)->execute([$path]);
            self::assertCount(1, $findings);
            self::assertSame('openai_style_key', $findings[0]->category);
            self::assertSame(1, $findings[0]->line);
            self::assertStringNotContainsString('sk-123', json_encode($findings[0]->toArray(), JSON_THROW_ON_ERROR));
        } finally {
            unlink($path);
        }
    }

    public function test_command_scans_only_source_and_does_not_print_environment_content(): void
    {
        self::assertSame(0, Artisan::call('security:source-scan', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('"safe":true', $output);
        self::assertStringNotContainsString('APP_KEY', $output);
    }
}
