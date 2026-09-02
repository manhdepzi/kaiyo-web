<?php

declare(strict_types=1);

namespace App\Modules\Growth\Infrastructure;

use App\Modules\Growth\Contracts\MerchantProjectionDestination;
use App\Modules\Growth\Data\MerchantProjectionChange;
use App\Modules\Growth\Data\MerchantPublishResult;

final class DisabledMerchantProjectionDestination implements MerchantProjectionDestination
{
    public function apply(MerchantProjectionChange $change): MerchantPublishResult
    {
        return MerchantPublishResult::failure('provider_unconfigured');
    }
}
