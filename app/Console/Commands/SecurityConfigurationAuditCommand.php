<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\AuditSecurityConfiguration;
use App\Modules\Foundation\Data\SecurityConfigurationCheck;
use Illuminate\Console\Command;

final class SecurityConfigurationAuditCommand extends Command
{
    protected $signature = 'security:configuration-audit
        {--json : Emit machine-readable check names and booleans without configuration values}
        {--production : Enforce the production profile even outside APP_ENV=production}';

    protected $description = 'Audit deploy-safe application configuration without reading or exposing secret values';

    public function handle(AuditSecurityConfiguration $auditor): int
    {
        $enforced = (bool) $this->option('production') || app()->environment('production');
        $checks = $auditor->execute();
        $violations = $enforced
            ? array_values(array_map(static fn (SecurityConfigurationCheck $check): string => $check->name, array_filter($checks, static fn (SecurityConfigurationCheck $check): bool => ! $check->passed)))
            : [];

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'profile' => $enforced ? 'production' : 'observational',
                'checks' => array_map(static fn (SecurityConfigurationCheck $check): array => $check->toArray(), $checks),
                'healthy' => $violations === [],
                'violations' => $violations,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Passed'], array_map(static fn (SecurityConfigurationCheck $check): array => [
                $check->name,
                $check->passed ? 'yes' : 'no',
            ], $checks));
            foreach ($violations as $violation) {
                $this->error("Production security configuration check failed: {$violation}");
            }
        }

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }
}
