<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\SalesCommercialDirectoryView;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SalesCommercialDirectoryReader
{
    public function orders(string $query, ?string $status): SalesCommercialDirectoryView
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $page = DB::table('orders')->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->when($status !== null, fn (Builder $builder) => $builder->where('orders.state', $status))
            ->when($normalized !== '', fn (Builder $builder) => $builder->where(fn (Builder $filter) => $filter
                ->where('orders.public_id', $query)->orWhere('customers.name_normalized', 'like', $normalized.'%')))
            ->select(['orders.*', 'customers.display_name as party_name'])
            ->orderByDesc('orders.placed_at')->orderByDesc('orders.id')->cursorPaginate(20);
        $records = [];
        foreach ($page->items() as $order) {
            if (! is_object($order)) {
                continue;
            }
            $data = get_object_vars($order);
            $records[] = [
                'public_id' => (string) $data['public_id'],
                'party' => (string) $data['party_name'],
                'state' => (string) $data['state'],
                'amount' => (int) $data['final_amount'],
                'currency' => (string) $data['currency'],
                'detail' => (string) $data['payment_method'].' · '.(string) $data['shipping_method'],
                'occurred_at' => (string) $data['placed_at'],
            ];
        }

        return new SalesCommercialDirectoryView($records, $query, $status, $page->nextCursor()?->encode(), $page->previousCursor()?->encode());
    }

    public function quotes(string $query, ?string $status): SalesCommercialDirectoryView
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $page = DB::table('quotes')->join('quote_revisions', 'quote_revisions.id', '=', 'quotes.current_revision_id')
            ->leftJoin('customers', 'customers.id', '=', 'quotes.customer_id')
            ->when($status !== null, fn (Builder $builder) => $builder->where('quote_revisions.state', $status))
            ->when($normalized !== '', fn (Builder $builder) => $builder->where(fn (Builder $filter) => $filter
                ->where('quotes.public_id', $query)->orWhere('customers.name_normalized', 'like', $normalized.'%')))
            ->select(['quotes.*', 'customers.display_name as party_name', 'quote_revisions.state as revision_state', 'quote_revisions.final_amount', 'quote_revisions.currency', 'quote_revisions.revision_no'])
            ->orderByDesc('quotes.id')->cursorPaginate(20);
        $records = [];
        foreach ($page->items() as $quote) {
            if (! is_object($quote)) {
                continue;
            }
            $data = get_object_vars($quote);
            $records[] = [
                'public_id' => (string) $data['public_id'],
                'party' => is_string($data['party_name']) ? $data['party_name'] : 'Khách chưa định danh',
                'state' => (string) $data['revision_state'],
                'amount' => (int) $data['final_amount'],
                'currency' => (string) $data['currency'],
                'detail' => 'Revision '.(int) $data['revision_no'],
                'occurred_at' => (string) $data['created_at'],
            ];
        }

        return new SalesCommercialDirectoryView($records, $query, $status, $page->nextCursor()?->encode(), $page->previousCursor()?->encode());
    }
}
