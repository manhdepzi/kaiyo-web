<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Application\Queries\AccountPortalReader;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Order\Application\Actions\ManageOrderCancellation;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountOrderCancellationController extends Controller
{
    public function __invoke(
        string $order,
        Request $request,
        AccountPortalReader $reader,
        ManageOrderCancellation $cancellations,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'request_key' => ['required', 'string', 'max:100'],
        ]);
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        abort_if($reader->order($account, $order) === null, 404);
        $ownedOrder = Order::query()->where('public_id', $order)->firstOrFail();

        try {
            $cancellations->request(
                $ownedOrder,
                $account,
                trim((string) $validated['reason']),
                (string) $validated['request_key'],
            );
        } catch (AuthorizationException) {
            abort(403);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['cancellation' => 'Chưa thể gửi yêu cầu hủy: '.$exception->getMessage()]);
        }

        return to_route('account.orders.show', $order)->with('status', 'Yêu cầu hủy đã được ghi nhận và đang chờ nhân viên có thẩm quyền xử lý.');
    }
}
