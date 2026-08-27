<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\SalesCustomerDirectoryView;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

final class SalesCustomerDirectoryReader
{
    public function read(string $query, ?string $status): SalesCustomerDirectoryView
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $customers = Customer::query()
            ->when($status !== null, fn (Builder $builder) => $builder->where('status', $status))
            ->when($normalized !== '', function (Builder $builder) use ($normalized): void {
                $builder->where(function (Builder $filter) use ($normalized): void {
                    $filter->where('name_normalized', 'like', $normalized.'%')
                        ->orWhere('primary_email_normalized', 'like', $normalized.'%')
                        ->orWhere('primary_phone_e164', 'like', $normalized.'%');
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        $items = array_values($customers->getCollection()->map(fn (Customer $customer): array => [
            'public_id' => $customer->public_id,
            'display_name' => $customer->display_name,
            'email' => is_string($customer->primary_email_display) ? $customer->primary_email_display : null,
            'phone' => is_string($customer->primary_phone_display) ? $customer->primary_phone_display : null,
            'status' => $customer->status,
            'updated_at' => $customer->updated_at?->toAtomString() ?? '',
        ])->all());

        $counts = Customer::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')
            ->pluck('aggregate', 'status')->map(fn (mixed $count): int => (int) $count)->all();

        return new SalesCustomerDirectoryView(
            $items,
            $counts,
            $query,
            $status,
            $customers->nextCursor()?->encode(),
            $customers->previousCursor()?->encode(),
        );
    }
}
