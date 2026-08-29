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
        if ($customer !== null) {
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
            ];
            $notifications = array_values(DB::table('notifications')->where('customer_id', $customer->getKey())
                ->where('channel', 'in_app')->where('state', 'sent')
                ->orderByDesc('created_at')->orderByDesc('id')->limit(20)
                ->get(['public_id', 'attributes', 'read_at', 'created_at'])
                ->map(function (object $row) use ($notificationTitles): array {
                    $attributes = json_decode((string) $row->attributes, true, 512, JSON_THROW_ON_ERROR);
                    $toState = is_string($attributes['to_state'] ?? null) ? $attributes['to_state'] : 'unknown';

                    return [
                        'public_id' => (string) $row->public_id,
                        'title' => $notificationTitles[$toState] ?? 'Trạng thái đơn hàng đã thay đổi',
                        'order_public_id' => is_string($attributes['order_public_id'] ?? null) ? $attributes['order_public_id'] : '',
                        'to_state' => $toState,
                        'is_read' => $row->read_at !== null,
                        'created_at' => (string) $row->created_at,
                    ];
                })->all());
        }
        $companies = array_values(DB::table('company_memberships')->join('companies', 'companies.id', '=', 'company_memberships.company_id')
            ->where('company_memberships.user_account_id', $account->getKey())
            ->where('company_memberships.status', 'active')->whereNull('company_memberships.ends_at')
            ->where('companies.status', 'active')->whereNull('companies.deleted_at')
            ->orderBy('companies.display_name')->limit(20)
            ->get(['companies.public_id', 'companies.display_name', 'company_memberships.status'])
            ->map(fn (object $row): array => [
                'public_id' => (string) $row->public_id,
                'display_name' => (string) $row->display_name,
                'status' => (string) $row->status,
            ])->all());

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
        );
    }
}
