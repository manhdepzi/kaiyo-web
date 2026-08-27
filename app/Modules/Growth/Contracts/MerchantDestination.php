<?php

declare(strict_types=1);

namespace App\Modules\Growth\Contracts;

use App\Modules\Growth\Data\MerchantFeedItem;
use App\Modules\Growth\Data\MerchantPublishResult;

interface MerchantDestination
{
    public function publish(MerchantFeedItem $item): MerchantPublishResult;
}
