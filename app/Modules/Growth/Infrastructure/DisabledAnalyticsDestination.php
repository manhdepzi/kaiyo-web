<?php

declare(strict_types=1);

namespace App\Modules\Growth\Infrastructure;

use App\Modules\Growth\Contracts\AnalyticsDestination;
use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Growth\Data\AnalyticsPublishResult;

final class DisabledAnalyticsDestination implements AnalyticsDestination
{
    public function publish(AnalyticsEvent $event, string $idempotencyKey): AnalyticsPublishResult
    {
        return AnalyticsPublishResult::failure('provider_unconfigured');
    }
}
