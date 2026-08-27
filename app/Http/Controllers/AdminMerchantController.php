<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Growth\Application\AdminMerchantBatchReader;
use App\Modules\Growth\Application\Jobs\ProcessMerchantFeedBatchJob;
use App\Modules\Growth\Application\StartMerchantFeedBatch;
use App\Modules\Growth\Infrastructure\Persistence\Models\MerchantFeedBatch;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminMerchantController extends Controller
{
    public function index(Request $request, AdminMerchantBatchReader $reader): View
    {
        $validated = $request->validate(['state' => ['nullable', 'in:pending,running,partial,completed,failed']]);
        $state = isset($validated['state']) ? (string) $validated['state'] : null;

        return view('admin.merchant', ['batches' => $reader->read($state), 'state' => $state]);
    }

    public function store(Request $request, StartMerchantFeedBatch $start): RedirectResponse
    {
        $validated = $request->validate([
            'configuration_revision' => ['required', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,99}\z/'],
            'operation_key' => ['required', 'string', 'min:8', 'max:200'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 403);
        $batch = $start->execute($actor, (string) $validated['configuration_revision'], (string) $validated['operation_key']);
        ProcessMerchantFeedBatchJob::dispatch($batch->public_id)->afterCommit();

        return redirect()->route('admin.merchant')->with('status', 'Merchant batch đã được đưa vào hàng đợi.');
    }

    public function retry(string $batch): RedirectResponse
    {
        $model = MerchantFeedBatch::query()->where('public_id', $batch)->firstOrFail();
        if (! in_array($model->state, ['partial', 'failed'], true)) {
            throw new DomainException('Only a partial or failed Merchant batch can be retried.');
        }
        ProcessMerchantFeedBatchJob::dispatch($model->public_id)->afterCommit();

        return redirect()->route('admin.merchant')->with('status', 'Merchant batch retry đã được đưa vào hàng đợi.');
    }
}
