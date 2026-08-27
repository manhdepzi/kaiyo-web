<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application\Jobs;

use App\Modules\Growth\Application\ProcessMerchantFeedBatch;
use App\Modules\Growth\Infrastructure\Persistence\Models\MerchantFeedBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessMerchantFeedBatchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly string $batchPublicId)
    {
        $this->onQueue('integrations');
    }

    public function uniqueId(): string
    {
        return 'merchant-feed:'.$this->batchPublicId;
    }

    public function handle(ProcessMerchantFeedBatch $processor): void
    {
        $batch = MerchantFeedBatch::query()->where('public_id', $this->batchPublicId)->firstOrFail();
        $processor->execute($batch);
    }
}
