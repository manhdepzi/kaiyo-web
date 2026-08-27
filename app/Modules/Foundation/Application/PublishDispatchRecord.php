<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Domain\Events\DispatchFactReleased;
use App\Modules\Foundation\Infrastructure\Persistence\Models\DispatchRecord;

final class PublishDispatchRecord
{
    public function publish(DispatchRecord $record): void
    {
        event(new DispatchFactReleased(
            $record->public_id,
            $record->event_type,
            $record->event_version,
            $record->aggregate_type,
            $record->aggregate_public_id,
            $record->payload,
        ));
    }
}
