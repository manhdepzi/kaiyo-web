<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Queries\AccountPortalReader;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AccountOrderController extends Controller
{
    public function __invoke(string $order, Request $request, AccountPortalReader $reader): View
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $view = $reader->order($account, $order);
        abort_if($view === null, 404);

        return view('account.order', [
            'order' => $view,
            'cancellationRequestKey' => (string) Str::ulid(),
        ]);
    }
}
