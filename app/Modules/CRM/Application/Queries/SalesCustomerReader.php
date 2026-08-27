<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\SalesCustomerView;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Support\Facades\DB;

final readonly class SalesCustomerReader
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function read(UserAccount $actor, string $publicId): ?SalesCustomerView
    {
        $customer = Customer::query()->where('public_id', $publicId)->first();
        if ($customer === null) {
            return null;
        }
        $scope = AuthorizationScope::customer('crm', (int) $customer->getKey());
        if (! $this->authorizer->allows($actor, 'crm.customers.read', $scope)) {
            return null;
        }

        $customerId = (int) $customer->getKey();
        $contacts = array_values(DB::table('contacts')->where('customer_id', $customerId)->whereNull('deleted_at')
            ->orderBy('name')->limit(50)->get(['name', 'email_display', 'phone_display', 'status'])
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'email' => $row->email_display === null ? null : (string) $row->email_display,
                'phone' => $row->phone_display === null ? null : (string) $row->phone_display,
                'status' => (string) $row->status,
            ])->all());
        $owner = DB::table('ownership_assignments')->join('user_accounts', 'user_accounts.id', '=', 'ownership_assignments.owner_user_account_id')
            ->where('ownership_assignments.customer_id', $customerId)->whereNull('ownership_assignments.ends_at')
            ->first(['user_accounts.email_display']);

        $canReadOrders = $this->authorizer->allows($actor, 'orders.read', AuthorizationScope::customer('orders', $customerId));
        $canReadQuotes = $this->authorizer->allows($actor, 'quotes.read', AuthorizationScope::customer('quotes', $customerId));
        $orders = $canReadOrders ? array_values(DB::table('orders')->where('customer_id', $customerId)
            ->orderByDesc('placed_at')->orderByDesc('id')->limit(10)
            ->get(['public_id', 'state', 'final_amount', 'currency', 'placed_at'])->map(fn (object $row): array => [
                'public_id' => (string) $row->public_id,
                'state' => (string) $row->state,
                'final_amount' => (int) $row->final_amount,
                'currency' => (string) $row->currency,
                'placed_at' => $row->placed_at === null ? null : (string) $row->placed_at,
            ])->all()) : [];
        $quotes = $canReadQuotes ? array_values(DB::table('quotes')->join('quote_revisions', 'quote_revisions.id', '=', 'quotes.current_revision_id')
            ->where('quotes.customer_id', $customerId)->orderByDesc('quotes.id')->limit(10)
            ->get(['quotes.public_id', 'quote_revisions.state', 'quote_revisions.final_amount', 'quote_revisions.currency', 'quote_revisions.revision_no'])
            ->map(fn (object $row): array => [
                'public_id' => (string) $row->public_id,
                'state' => (string) $row->state,
                'final_amount' => (int) $row->final_amount,
                'currency' => (string) $row->currency,
                'revision' => (int) $row->revision_no,
            ])->all()) : [];

        return new SalesCustomerView(
            $customer->public_id,
            $customer->display_name,
            is_string($customer->primary_email_display) ? $customer->primary_email_display : null,
            is_string($customer->primary_phone_display) ? $customer->primary_phone_display : null,
            $customer->status,
            $owner === null ? null : (string) $owner->email_display,
            $contacts,
            $orders,
            $quotes,
            $canReadOrders,
            $canReadQuotes,
        );
    }
}
