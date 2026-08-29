<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class AccountPortalView
{
    /**
     * @param  array{public_id:string,display_name:string,email:?string,phone:?string,status:string,version:int}|null  $customer
     * @param  list<array{public_id:string,state:string,final_amount:int,currency:string,placed_at:?string}>  $orders
     * @param  list<array{public_id:string,state:string,final_amount:int,currency:string,revision:int}>  $quotes
     * @param  list<array{public_id:string,display_name:string,status:string}>  $companies
     * @param  list<array{public_id:string,title:string,order_public_id:string,to_state:string,is_read:bool,created_at:string}>  $notifications
     */
    public function __construct(
        public string $accountEmail,
        public ?array $customer,
        public array $orders,
        public array $quotes,
        public array $companies,
        public array $notifications,
    ) {}
}
