<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CMS\Application\Actions\RunCmsPublicationSchedule;
use App\Modules\CMS\Infrastructure\Persistence\Models\PublicationSchedule;
use Illuminate\Console\Command;

final class RunCmsPublicationSchedules extends Command
{
    protected $signature = 'cms:run-publication-schedules {--limit=100 : Maximum due operations per run}';

    protected $description = 'Run due CMS publication operations idempotently';

    public function handle(RunCmsPublicationSchedule $action): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $schedules = PublicationSchedule::query()->where('state', 'pending')->where('due_at', '<=', now())
            ->orderBy('due_at')->orderBy('id')->limit($limit)->get();
        foreach ($schedules as $schedule) {
            $action->execute($schedule);
        }
        $this->info('Processed '.$schedules->count().' due publication operation(s).');

        return self::SUCCESS;
    }
}
