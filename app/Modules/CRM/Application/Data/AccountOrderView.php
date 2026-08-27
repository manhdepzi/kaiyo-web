<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class AccountOrderView
{
    /**
     * @param  list<array{sku:string,name:string,quantity:string,line_amount:int}>  $lines
     * @param  list<array{from:?string,to:string,occurred_at:string}>  $history
     */
    public function __construct(
        public string $publicId,
        public string $state,
        public string $currency,
        public int $merchandiseAmount,
        public int $taxAmount,
        public int $shippingAmount,
        public int $finalAmount,
        public string $paymentMethod,
        public ?string $paymentState,
        public ?string $shipmentState,
        public ?string $cancellationState,
        public array $lines,
        public array $history,
    ) {}
}
