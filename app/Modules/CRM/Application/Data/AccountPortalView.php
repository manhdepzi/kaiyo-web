<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Data;

final readonly class AccountPortalView
{
    /**
     * @param  array{public_id:string,display_name:string,email:?string,phone:?string,status:string,version:int}|null  $customer
     * @param  list<array{public_id:string,state:string,final_amount:int,currency:string,placed_at:?string}>  $orders
     * @param  list<array{public_id:string,state:string,final_amount:int,currency:string,revision:int}>  $quotes
     * @param  list<array{public_id:string,display_name:string,status:string,capabilities:list<string>}>  $companies
     * @param  list<array{public_id:string,title:string,subject_type:string,subject_public_id:string,order_public_id:string,to_state:string,is_read:bool,created_at:string}>  $notifications
     * @param  list<array{public_id:string,label:string,recipient_name:string,company_name:?string,tax_code:?string,address_line_1:string,address_line_2:?string,locality:?string,subdivision:?string,postal_code:?string,country_code:string,phone:?string,is_default_shipping:bool,is_default_billing:bool,version:int}>  $addresses
     * @param  list<array{public_id:string,name:string,slug:string,category:string,saved_at:string}>  $wishlist
     * @param  list<array{public_id:string,product_name:string,product_slug:string,rating:int,title:string,status:string,version:int}>  $reviews
     * @param  array{in_app:bool,email:bool,sms:bool,version:int}  $notificationPreferences
     */
    public function __construct(
        public string $accountEmail,
        public ?array $customer,
        public array $orders,
        public array $quotes,
        public array $companies,
        public array $notifications,
        public array $addresses,
        public array $wishlist,
        public array $reviews,
        public array $notificationPreferences,
    ) {}
}
