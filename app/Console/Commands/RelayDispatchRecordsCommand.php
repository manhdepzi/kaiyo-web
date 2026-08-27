<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\RelayDispatchRecords;
use Illuminate\Console\Command;

final class RelayDispatchRecordsCommand extends Command
{
    protected $signature = 'outbox:relay {--limit=100 : Maximum dispatch records per run}';

    protected $description = 'Relay committed dispatch records idempotently';

    public function handle(RelayDispatchRecords $relay): int
    {
        $result = $relay->execute((int) $this->option('limit'));
        $this->info("Published {$result['published']}; failed {$result['failed']} dispatch record(s).");

        return self::SUCCESS;
    }
}
