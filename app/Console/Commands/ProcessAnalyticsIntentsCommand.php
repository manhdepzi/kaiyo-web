<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Growth\Application\ProcessAnalyticsIntents;
use Illuminate\Console\Command;

final class ProcessAnalyticsIntentsCommand extends Command
{
    protected $signature = 'analytics:process-intents {--limit=25}';

    protected $description = 'Process durable consent-aware analytics event intents';

    public function handle(ProcessAnalyticsIntents $processor): int
    {
        $processed = $processor->execute((int) $this->option('limit'));
        $this->info("Processed {$processed} analytics intent(s).");

        return self::SUCCESS;
    }
}
