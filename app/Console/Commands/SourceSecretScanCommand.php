<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\ScanSourceSecrets;
use App\Modules\Foundation\Data\SourceSecretFinding;
use Illuminate\Console\Command;

final class SourceSecretScanCommand extends Command
{
    protected $signature = 'security:source-scan {--json : Emit file, line and finding category without source content}';

    protected $description = 'Fail on likely hard-coded credentials in tracked application source without reading environment files';

    public function handle(ScanSourceSecrets $scanner): int
    {
        $findings = $scanner->execute();
        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'safe' => $findings === [],
                'findings' => array_map(static fn (SourceSecretFinding $finding): array => $finding->toArray(), $findings),
            ], JSON_THROW_ON_ERROR));
        } elseif ($findings === []) {
            $this->info('No likely hard-coded credentials found in scanned source.');
        } else {
            foreach ($findings as $finding) {
                $this->error("{$finding->category}: {$finding->path}:{$finding->line}");
            }
        }

        return $findings === [] ? self::SUCCESS : self::FAILURE;
    }
}
