<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\SalesLeadDirectoryView;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

final class SalesLeadDirectoryReader
{
    public function read(string $query, ?string $status): SalesLeadDirectoryView
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $leads = Lead::query()
            ->when($status !== null, fn (Builder $builder) => $builder->where('status', $status))
            ->when($normalized !== '', function (Builder $builder) use ($normalized): void {
                $builder->where(function (Builder $filter) use ($normalized): void {
                    $filter->where('name_normalized', 'like', $normalized.'%')
                        ->orWhere('email_normalized', 'like', $normalized.'%')
                        ->orWhere('phone_e164', 'like', $normalized.'%');
                });
            })
            ->orderByDesc('updated_at')->orderByDesc('id')->cursorPaginate(20);

        $items = array_values($leads->getCollection()->map(fn (Lead $lead): array => [
            'public_id' => $lead->public_id,
            'display_name' => $lead->display_name,
            'company' => is_string($lead->company_name) ? $lead->company_name : null,
            'email' => is_string($lead->email_display) ? $lead->email_display : null,
            'phone' => is_string($lead->phone_display) ? $lead->phone_display : null,
            'source' => $lead->source,
            'status' => $lead->status,
            'updated_at' => $lead->updated_at?->toAtomString() ?? '',
        ])->all());
        $counts = Lead::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')
            ->pluck('aggregate', 'status')->map(fn (mixed $count): int => (int) $count)->all();

        return new SalesLeadDirectoryView(
            $items,
            $counts,
            $query,
            $status,
            $leads->nextCursor()?->encode(),
            $leads->previousCursor()?->encode(),
        );
    }
}
