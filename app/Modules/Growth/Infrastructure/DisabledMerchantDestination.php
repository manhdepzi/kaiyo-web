<?php

declare(strict_types=1);

namespace App\Modules\Growth\Infrastructure;

use App\Modules\Growth\Contracts\MerchantDestination;
use App\Modules\Growth\Data\MerchantFeedItem;
use App\Modules\Growth\Data\MerchantPublishResult;

final class DisabledMerchantDestination implements MerchantDestination
{
    public function publish(MerchantFeedItem $item): MerchantPublishResult
    {
        return MerchantPublishResult::failure('provider_unconfigured');
    }
}
