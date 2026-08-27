<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PublicCheckoutCompleteController extends Controller
{
    public function __invoke(string $order, Request $request): View
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $customer = Customer::query()->where('user_account_id', $account->getKey())->where('status', 'active')->first();
        abort_if($customer === null, 404);
        $record = Order::query()->where('public_id', $order)->where('customer_id', $customer->getKey())->firstOrFail();

        return view('public.checkout-complete', ['order' => [
            'publicId' => $record->public_id,
            'state' => $record->state,
            'currency' => $record->currency,
            'finalAmount' => $record->final_amount,
            'paymentMethod' => $record->payment_method,
        ]]);
    }
}
