<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Support;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesOrder
{
    private function authorizeOrder(PermissionAuthorizer $authorizer, UserAccount $actor, Order $order, string $permission): void
    {
        $customer = Customer::query()->findOrFail($order->customer_id);
        $scope = AuthorizationScope::customer('orders', (int) $customer->getKey(), $customer->user_account_id === null ? null : (int) $customer->user_account_id);
        if (! $authorizer->allows($actor, $permission, $scope)) {
            throw new AuthorizationException('The actor cannot perform this Order operation.');
        }
    }
}
