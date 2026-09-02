<?php

declare(strict_types=1);

namespace App\Modules\Growth\Contracts;

use App\Modules\Growth\Data\MerchantProjectionChange;
use App\Modules\Growth\Data\MerchantPublishResult;

interface MerchantProjectionDestination
{
    public function apply(MerchantProjectionChange $change): MerchantPublishResult;
}
