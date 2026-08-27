<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\SalesCompanyDirectoryView;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use Illuminate\Database\Eloquent\Builder;

final class SalesCompanyDirectoryReader
{
    public function read(string $query, ?string $status): SalesCompanyDirectoryView
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $companies = Company::query()
            ->when($status !== null, fn (Builder $builder) => $builder->where('status', $status))
            ->when($normalized !== '', fn (Builder $builder) => $builder->where(fn (Builder $filter) => $filter
                ->where('name_normalized', 'like', $normalized.'%')
                ->orWhere('tax_code_normalized', 'like', $normalized.'%')))
            ->orderByDesc('updated_at')->orderByDesc('id')->cursorPaginate(20);
        $items = array_values($companies->getCollection()->map(fn (Company $company): array => [
            'public_id' => $company->public_id,
            'display_name' => $company->display_name,
            'legal_name' => (string) $company->legal_name,
            'tax_code' => is_string($company->tax_code_display) ? $company->tax_code_display : null,
            'status' => $company->status,
            'updated_at' => $company->updated_at?->toAtomString() ?? '',
        ])->all());
        $counts = Company::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')
            ->pluck('aggregate', 'status')->map(fn (mixed $count): int => (int) $count)->all();

        return new SalesCompanyDirectoryView($items, $counts, $query, $status, $companies->nextCursor()?->encode(), $companies->previousCursor()?->encode());
    }
}
