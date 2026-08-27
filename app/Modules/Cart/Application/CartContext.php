<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Application\Data\ResolvedCart;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class CartContext
{
    public function __construct(private CartService $carts) {}

    public function resolve(?string $guestToken, ?UserAccount $account): ResolvedCart
    {
        $customer = $account === null ? null : Customer::query()
            ->where('user_account_id', $account->getKey())
            ->where('status', 'active')
            ->first();

        if ($customer !== null) {
            if ($guestToken !== null) {
                try {
                    return new ResolvedCart($this->carts->mergeGuestIntoCustomer($guestToken, $customer));
                } catch (ModelNotFoundException|DomainException) {
                    // A stale/tampered guest identity must not block the authenticated cart.
                }
            }

            return new ResolvedCart($this->carts->forCustomer($customer));
        }

        if ($guestToken !== null) {
            try {
                return new ResolvedCart($this->carts->resolveGuest($guestToken));
            } catch (ModelNotFoundException|DomainException) {
                // Rotate an unresolvable opaque identity without disclosing why it failed.
            }
        }

        $guest = $this->carts->createGuest();

        return new ResolvedCart($guest->cart, $guest->token);
    }
}
