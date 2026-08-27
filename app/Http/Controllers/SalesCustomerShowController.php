<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Queries\SalesCustomerReader;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SalesCustomerShowController
{
    public function __invoke(string $customer, Request $request, SalesCustomerReader $reader): View
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $view = $reader->read($account, $customer);
        abort_unless($view !== null, 404);

        return view('sales.customer', ['customer' => $view]);
    }
}
