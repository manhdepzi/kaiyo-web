<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;

final readonly class LeadConversionResult
{
    public function __construct(public Lead $lead, public ?Customer $customer, public ?Company $company) {}
}
