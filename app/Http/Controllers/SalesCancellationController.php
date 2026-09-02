<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Order\Application\Actions\ManageOrderCancellation;
use App\Modules\Order\Infrastructure\Persistence\Models\CancellationRequest;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SalesCancellationController extends Controller
{
    public function index(): View
    {
        $requests = DB::table('cancellation_requests as cancellations')
            ->join('orders', 'orders.id', '=', 'cancellations.order_id')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('cancellations.state', 'requested')
            ->orderBy('cancellations.created_at')
            ->limit(100)
            ->get([
                'cancellations.public_id', 'cancellations.reason', 'cancellations.lock_version',
                'cancellations.created_at', 'orders.public_id as order_public_id', 'orders.state as order_state',
                'orders.final_amount', 'orders.currency', 'customers.display_name as customer_name',
            ]);

        return view('sales.cancellations', ['requests' => $requests]);
    }

    public function decide(
        string $cancellation,
        Request $request,
        ManageOrderCancellation $cancellations,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'deny'])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'decision_key' => ['required', 'string', 'max:100'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $record = CancellationRequest::query()->where('public_id', $cancellation)->firstOrFail();

        try {
            $cancellations->decide(
                $record,
                $actor,
                $validated['decision'] === 'approve',
                trim((string) $validated['reason']),
                (string) $validated['decision_key'],
                (int) $validated['expected_version'],
            );
        } catch (AuthorizationException) {
            abort(403);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['cancellation' => 'Chưa thể xử lý yêu cầu: '.$exception->getMessage()]);
        }

        return to_route('sales.cancellations')->with('status', 'Quyết định hủy đơn đã được ghi nhận.');
    }
}
