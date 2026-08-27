<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Search\Application\SearchService;
use App\Modules\Search\Domain\SearchQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PublicSearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $term = (string) ($validated['q'] ?? '');
        $page = (int) ($validated['page'] ?? 1);

        return view('public.search', [
            'term' => $term,
            'result' => $search->search(new SearchQuery($term, page: $page, perPage: 20)),
        ]);
    }
}
