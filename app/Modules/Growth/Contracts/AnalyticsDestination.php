<?php

declare(strict_types=1);

namespace App\Modules\Growth\Contracts;

use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Growth\Data\AnalyticsPublishResult;

interface AnalyticsDestination
{
    public function publish(AnalyticsEvent $event, string $idempotencyKey): AnalyticsPublishResult;
}
