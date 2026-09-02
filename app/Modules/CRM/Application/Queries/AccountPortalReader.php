<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\AccountOrderView;
use App\Modules\CRM\Application\Data\AccountPortalView;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Support\Facades\DB;

final class AccountPortalReader
{
    public function order(UserAccount $account, string $publicId): ?AccountOrderView
    {
        $customerId = Customer::query()->where('user_account_id', $account->getKey())->where('status', 'active')->value('id');
        if (! is_int($customerId)) {
            return null;
        }
        $order = DB::table('orders')->where('public_id', $publicId)->where('customer_id', $customerId)->first();
        if ($order === null) {
            return null;
        }
        $payment = DB::table('payments')->where('order_id', $order->id)->first(['state']);
        $shipment = DB::table('shipments')->where('order_id', $order->id)->first(['state']);
        $cancellation = DB::table('cancellation_requests')->where('order_id', $order->id)->orderByDesc('id')->first(['state']);
        $lines = array_values(DB::table('order_lines')->where('order_id', $order->id)->orderBy('id')
            ->get(['sku', 'name', 'quantity', 'line_amount'])->map(fn (object $row): array => [
                'sku' => (string) $row->sku, 'name' => (string) $row->name,
                'quantity' => (string) $row->quantity, 'line_amount' => (int) $row->line_amount,
            ])->all());
        $history = array_values(DB::table('order_status_history')->where('order_id', $order->id)->orderBy('occurred_at')->orderBy('id')
            ->get(['from_state', 'to_state', 'occurred_at'])->map(fn (object $row): array => [
                'from' => $row->from_state === null ? null : (string) $row->from_state,
                'to' => (string) $row->to_state, 'occurred_at' => (string) $row->occurred_at,
            ])->all());

        return new AccountOrderView(
            (string) $order->public_id, (string) $order->state, (string) $order->currency,
            (int) $order->merchandise_amount, (int) $order->tax_amount, (int) $order->shipping_amount,
            (int) $order->final_amount, (string) $order->payment_method,
            $payment === null ? null : (string) $payment->state,
            $shipment === null ? null : (string) $shipment->state,
            $cancellation === null ? null : (string) $cancellation->state,
            $lines, $history,
        );
    }

    public function read(UserAccount $account): AccountPortalView
    {
        $customer = Customer::query()->where('user_account_id', $account->getKey())->first();
        $orders = [];
        $quotes = [];
        $notifications = [];
        $addresses = [];
        $wishlist = [];
        $reviews = [];
        $notificationPreferences = ['in_app' => true, 'email' => false, 'sms' => false, 'version' => 0];
        if ($customer !== null) {
            $preference = DB::table('notification_preferences')->where('customer_id', $customer->getKey())->first([
                'order_updates_email', 'order_updates_sms', 'lock_version',
            ]);
            if ($preference !== null) {
                $notificationPreferences = [
                    'in_app' => true,
                    'email' => (bool) $preference->order_updates_email,
                    'sms' => (bool) $preference->order_updates_sms,
                    'version' => (int) $preference->lock_version,
                ];
            }
            $reviews = array_values(DB::table('product_reviews as reviews')->join('products', 'products.id', '=', 'reviews.product_id')
                ->where('reviews.customer_id', $customer->getKey())->orderByDesc('reviews.submitted_at')->limit(50)
                ->get(['reviews.public_id', 'reviews.rating', 'reviews.title', 'reviews.status', 'reviews.lock_version', 'products.name as product_name', 'products.slug as product_slug'])
                ->map(fn (object $row): array => [
                    'public_id' => (string) $row->public_id, 'product_name' => (string) $row->product_name,
                    'product_slug' => (string) $row->product_slug, 'rating' => (int) $row->rating,
                    'title' => (string) $row->title, 'status' => (string) $row->status, 'version' => (int) $row->lock_version,
                ])->all());
            $wishlist = array_values(DB::table('customer_wishlist_items')
                ->join('products', 'products.id', '=', 'customer_wishlist_items.product_id')
                ->join('categories', 'categories.id', '=', 'products.primary_category_id')
                ->where('customer_wishlist_items.customer_id', $customer->getKey())
                ->where('products.status', 'active')->whereNull('products.deleted_at')
                ->where('categories.status', 'active')->whereNull('categories.deleted_at')
                ->orderByDesc('customer_wishlist_items.created_at')->orderByDesc('customer_wishlist_items.id')->limit(100)
                ->get(['products.public_id', 'products.name', 'products.slug', 'categories.name as category', 'customer_wishlist_items.created_at'])
                ->map(fn (object $row): array => [
                    'public_id' => (string) $row->public_id,
                    'name' => (string) $row->name,
                    'slug' => (string) $row->slug,
                    'category' => (string) $row->category,
                    'saved_at' => (string) $row->created_at,
                ])->all());
            $addresses = array_values(DB::table('customer_addresses')->where('customer_id', $customer->getKey())
                ->where('status', 'active')->whereNull('deleted_at')
                ->orderByDesc('is_default_shipping')->orderByDesc('is_default_billing')->orderBy('id')->limit(20)
                ->get(['public_id', 'label', 'recipient_name', 'company_name', 'tax_code', 'address_line_1', 'address_line_2', 'locality', 'subdivision', 'postal_code', 'country_code', 'phone', 'is_default_shipping', 'is_default_billing', 'lock_version'])
                ->map(fn (object $row): array => [
                    'public_id' => (string) $row->public_id,
                    'label' => (string) $row->label,
                    'recipient_name' => (string) $row->recipient_name,
                    'company_name' => is_string($row->company_name) ? $row->company_name : null,
                    'tax_code' => is_string($row->tax_code) ? $row->tax_code : null,
                    'address_line_1' => (string) $row->address_line_1,
                    'address_line_2' => is_string($row->address_line_2) ? $row->address_line_2 : null,
                    'locality' => is_string($row->locality) ? $row->locality : null,
                    'subdivision' => is_string($row->subdivision) ? $row->subdivision : null,
                    'postal_code' => is_string($row->postal_code) ? $row->postal_code : null,
                    'country_code' => (string) $row->country_code,
                    'phone' => is_string($row->phone) ? $row->phone : null,
                    'is_default_shipping' => (bool) $row->is_default_shipping,
                    'is_default_billing' => (bool) $row->is_default_billing,
                    'version' => (int) $row->lock_version,
                ])->all());
            $orders = array_values(DB::table('orders')->where('customer_id', $customer->getKey())
                ->orderByDesc('placed_at')->orderByDesc('id')->limit(10)
                ->get(['public_id', 'state', 'final_amount', 'currency', 'placed_at'])
                ->map(fn (object $row): array => [
                    'public_id' => (string) $row->public_id,
                    'state' => (string) $row->state,
                    'final_amount' => (int) $row->final_amount,
                    'currency' => (string) $row->currency,
                    'placed_at' => $row->placed_at === null ? null : (string) $row->placed_at,
                ])->all());
            $quotes = array_values(DB::table('quotes')->join('quote_revisions', 'quote_revisions.id', '=', 'quotes.current_revision_id')
                ->where('quotes.customer_id', $customer->getKey())->orderByDesc('quotes.id')->limit(10)
                ->get(['quotes.public_id', 'quote_revisions.state', 'quote_revisions.final_amount', 'quote_revisions.currency', 'quote_revisions.revision_no'])
                ->map(fn (object $row): array => [
                    'public_id' => (string) $row->public_id,
                    'state' => (string) $row->state,
                    'final_amount' => (int) $row->final_amount,
                    'currency' => (string) $row->currency,
                    'revision' => (int) $row->revision_no,
                ])->all());
            $notificationTitles = [
                'confirmed' => 'Đơn hàng đã được xác nhận',
                'processing' => 'Đơn hàng đang được xử lý',
                'packed' => 'Đơn hàng đã đóng gói',
                'shipping' => 'Đơn hàng đang được giao',
                'delivered' => 'Đơn hàng đã giao',
                'completed' => 'Đơn hàng đã hoàn tất',
                'cancelled' => 'Đơn hàng đã hủy',
                'quotation.sent' => 'Báo giá đã sẵn sàng',
                'quotation.accepted' => 'Báo giá đã được chấp nhận',
                'quotation.rejected' => 'Báo giá đã bị từ chối',
                'quotation.expired' => 'Báo giá đã hết hạn',
                'quotation.converted' => 'Báo giá đã được chuyển thành đơn hàng',
                'shipment.booked' => 'Lô hàng đã được đặt với đơn vị vận chuyển',
                'shipment.booking_unknown' => 'Lô hàng đang chờ đối soát vận chuyển',
                'shipment.packed' => 'Lô hàng đã được đóng gói',
                'shipment.dispatched' => 'Lô hàng đã xuất kho',
                'shipment.in_transit' => 'Lô hàng đang được vận chuyển',
                'shipment.exception' => 'Lô hàng cần được xử lý',
                'shipment.delivered' => 'Lô hàng đã giao thành công',
            ];
            $notifications = array_values(DB::table('notifications')->where('customer_id', $customer->getKey())
                ->where('channel', 'in_app')->where('state', 'sent')
                ->orderByDesc('created_at')->orderByDesc('id')->limit(20)
                ->get(['public_id', 'template_key', 'attributes', 'read_at', 'created_at'])
                ->map(function (object $row) use ($notificationTitles): array {
                    $attributes = json_decode((string) $row->attributes, true, 512, JSON_THROW_ON_ERROR);
                    $toState = is_string($attributes['to_state'] ?? null) ? $attributes['to_state'] : 'unknown';
                    $template = (string) $row->template_key;
                    $subjectType = str_starts_with($template, 'quotation.') ? 'quotation' : (str_starts_with($template, 'shipment.') ? 'shipment' : 'order');
                    $subjectPublicId = match ($subjectType) {
                        'quotation' => is_string($attributes['quote_public_id'] ?? null) ? $attributes['quote_public_id'] : '',
                        'shipment' => is_string($attributes['shipment_public_id'] ?? null) ? $attributes['shipment_public_id'] : '',
                        default => is_string($attributes['order_public_id'] ?? null) ? $attributes['order_public_id'] : '',
                    };

                    return [
                        'public_id' => (string) $row->public_id,
                        'title' => $notificationTitles[$template] ?? $notificationTitles[$toState] ?? 'Trạng thái nghiệp vụ đã thay đổi',
                        'subject_type' => $subjectType,
                        'subject_public_id' => $subjectPublicId,
                        'order_public_id' => is_string($attributes['order_public_id'] ?? null) ? $attributes['order_public_id'] : '',
                        'to_state' => $toState,
                        'is_read' => $row->read_at !== null,
                        'created_at' => (string) $row->created_at,
                    ];
                })->all());
        }
        $membershipRows = DB::table('company_memberships')->join('companies', 'companies.id', '=', 'company_memberships.company_id')
            ->where('company_memberships.user_account_id', $account->getKey())
            ->where('company_memberships.status', 'active')
            ->where('company_memberships.starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('company_memberships.ends_at')->orWhere('company_memberships.ends_at', '>', now()))
            ->where('companies.status', 'active')->whereNull('companies.deleted_at')
            ->orderBy('companies.display_name')->limit(20)
            ->get(['company_memberships.id as membership_id', 'companies.public_id', 'companies.display_name', 'company_memberships.status']);
        $companiesByMembership = [];
        foreach ($membershipRows as $row) {
            $companiesByMembership[(int) $row->membership_id] = [
                'public_id' => (string) $row->public_id,
                'display_name' => (string) $row->display_name,
                'status' => (string) $row->status,
                'capabilities' => [],
            ];
        }
        if ($companiesByMembership !== []) {
            $capabilityRows = DB::table('company_member_capabilities as capabilities')
                ->join('permission_definitions as permissions', 'permissions.id', '=', 'capabilities.permission_definition_id')
                ->whereIn('capabilities.company_membership_id', array_keys($companiesByMembership))
                ->whereNull('capabilities.revoked_at')->where('permissions.status', 'active')
                ->orderBy('permissions.code')
                ->get(['capabilities.company_membership_id', 'permissions.code']);
            foreach ($capabilityRows as $row) {
                $membershipId = (int) $row->company_membership_id;
                if (isset($companiesByMembership[$membershipId])) {
                    $companiesByMembership[$membershipId]['capabilities'][] = (string) $row->code;
                }
            }
        }
        $companies = array_values($companiesByMembership);

        return new AccountPortalView(
            $account->email_display,
            $customer === null ? null : [
                'public_id' => $customer->public_id,
                'display_name' => $customer->display_name,
                'email' => is_string($customer->primary_email_display) ? $customer->primary_email_display : null,
                'phone' => is_string($customer->primary_phone_display) ? $customer->primary_phone_display : null,
                'status' => $customer->status,
                'version' => $customer->lock_version,
            ],
            $orders,
            $quotes,
            $companies,
            $notifications,
            $addresses,
            $wishlist,
            $reviews,
            $notificationPreferences,
        );
    }
}
