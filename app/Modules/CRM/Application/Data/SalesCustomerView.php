<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class SalesCustomerView
{
    /**
     * @param  list<array{name:string,email:?string,phone:?string,status:string}>  $contacts
     * @param  list<array{public_id:string,state:string,final_amount:int,currency:string,placed_at:?string}>  $orders
     * @param  list<array{public_id:string,state:string,final_amount:int,currency:string,revision:int}>  $quotes
     */
    public function __construct(
        public string $publicId,
        public string $displayName,
        public ?string $email,
        public ?string $phone,
        public string $status,
        public ?string $ownerEmail,
        public array $contacts,
        public array $orders,
        public array $quotes,
        public bool $canReadOrders,
        public bool $canReadQuotes,
    ) {}
}
