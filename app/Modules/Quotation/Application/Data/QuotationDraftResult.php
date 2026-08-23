<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Data;

use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;

final readonly class QuotationDraftResult
{
    public function __construct(public Quote $quote, public QuoteRevision $revision) {}
}
