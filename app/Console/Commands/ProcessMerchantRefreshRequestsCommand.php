<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Growth\Application\ProcessMerchantRefreshRequests;
use Illuminate\Console\Command;

final class ProcessMerchantRefreshRequestsCommand extends Command
{
    protected $signature = 'merchant:process-refreshes {--limit=25 : Maximum refresh requests to claim}';

    protected $description = 'Process bounded provider-neutral Merchant projection refresh requests';

    public function handle(ProcessMerchantRefreshRequests $processor): int
    {
        $processed = $processor->execute((int) $this->option('limit'));
        $this->info("Processed {$processed} Merchant refresh request(s).");

        return self::SUCCESS;
    }
}
