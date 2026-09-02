<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ManageOwnWishlist
{
    public function add(UserAccount $account, string $productPublicId): void
    {
        DB::transaction(function () use ($account, $productPublicId): void {
            $customer = $this->customer($account);
            $product = Product::query()->where('public_id', $productPublicId)
                ->where('status', 'active')->whereNull('deleted_at')->firstOrFail();
            $exists = DB::table('customer_wishlist_items')->where('customer_id', $customer->getKey())
                ->where('product_id', $product->getKey())->exists();
            if (! $exists && DB::table('customer_wishlist_items')->where('customer_id', $customer->getKey())->count() >= 100) {
                throw new DomainException('A customer may save at most 100 products.');
            }
            DB::table('customer_wishlist_items')->insertOrIgnore([
                'customer_id' => $customer->getKey(),
                'product_id' => $product->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function remove(UserAccount $account, string $productPublicId): void
    {
        DB::transaction(function () use ($account, $productPublicId): void {
            $customer = $this->customer($account);
            $productId = Product::withTrashed()->where('public_id', $productPublicId)->value('id');
            if (! is_int($productId)) {
                abort(404);
            }
            DB::table('customer_wishlist_items')->where('customer_id', $customer->getKey())
                ->where('product_id', $productId)->delete();
        }, 3);
    }

    private function customer(UserAccount $account): Customer
    {
        if (! $account->isActive() || $account->email_verified_at === null) {
            throw new DomainException('Wishlist management requires an active verified account.');
        }

        $customer = Customer::query()->where('user_account_id', $account->getKey())
            ->where('status', 'active')->lockForUpdate()->first();
        if ($customer === null) {
            throw new DomainException('Complete the customer profile before saving products.');
        }

        return $customer;
    }
}
