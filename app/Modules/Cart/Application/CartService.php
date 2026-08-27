<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\GuestCart;
use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;
use App\Modules\Cart\Infrastructure\Persistence\Models\CartLine;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Pricing\Application\Services\DatabasePricingResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CartService
{
    public function __construct(private DatabasePricingResolver $pricing) {}

    public function createGuest(): GuestCart
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $cart = Cart::query()->create(['guest_token_hash' => $this->tokenHash($token), 'status' => 'active', 'expires_at' => now()->addDays(30)])->refresh();

        return new GuestCart($cart, $token);
    }

    public function resolveGuest(string $token): Cart
    {
        return Cart::query()->where('guest_token_hash', $this->tokenHash($token))->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->firstOrFail();
    }

    public function forCustomer(Customer $customer): Cart
    {
        return Cart::query()->firstOrCreate(['customer_id' => $customer->getKey(), 'status' => 'active'], ['guest_token_hash' => null])->refresh();
    }

    public function putLine(Cart $cart, Variant $variant, string $quantity, string $operationKey, int $expectedVersion): Cart
    {
        $parsed = InventoryQuantity::from($quantity);
        $factor = 10 ** (4 - $variant->quantity_scale);
        if ($parsed->units <= 0 || $parsed->units % $factor !== 0 || trim($operationKey) === '') {
            throw new DomainException('Cart quantity is invalid for the Variant scale.');
        }
        $requestHash = hash('sha256', $cart->getKey().'|'.$variant->getKey().'|'.$parsed->decimal(), true);

        return DB::transaction(function () use ($cart, $variant, $parsed, $operationKey, $expectedVersion, $requestHash): Cart {
            $operation = DB::table('cart_operations')->where('operation_key', $operationKey)->first();
            if ($operation !== null) {
                if (! hash_equals((string) $operation->request_hash, $requestHash)) {
                    throw new DomainException('Idempotency key was reused with a different Cart mutation.');
                }

                return Cart::query()->with('lines')->findOrFail((int) $operation->result_cart_id);
            }
            $locked = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active' || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Cart is stale or inactive.');
            }
            $line = CartLine::query()->where('cart_id', $locked->getKey())->where('variant_id', $variant->getKey())->lockForUpdate()->first();
            if ($line === null) {
                CartLine::query()->create(['cart_id' => $locked->getKey(), 'variant_id' => $variant->getKey(), 'quantity' => $parsed->decimal()]);
            } else {
                $line->forceFill(['quantity' => $parsed->decimal(), 'advisory_unit_amount' => null, 'advisory_line_amount' => null, 'advisory_available_qty' => null, 'advisory_status' => 'stale', 'previewed_at' => null, 'lock_version' => $line->lock_version + 1])->save();
            }
            $locked->forceFill(['lock_version' => $expectedVersion + 1])->save();
            DB::table('cart_operations')->insert(['operation_key' => $operationKey, 'request_hash' => $requestHash, 'result_cart_id' => $locked->getKey(), 'created_at' => now()]);

            return $locked->refresh()->load('lines');
        }, 3);
    }

    public function putPublicLine(Cart $cart, string $variantPublicId, string $quantity, string $operationKey, int $expectedVersion): Cart
    {
        $variant = Variant::query()
            ->where('public_id', $variantPublicId)
            ->where('status', 'active')
            ->whereHas('product', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('category', fn ($category) => $category->where('status', 'active'))
                ->where(fn ($product) => $product->whereNull('brand_id')->orWhereHas('brand', fn ($brand) => $brand->where('status', 'active'))))
            ->firstOrFail();

        return $this->putLine($cart, $variant, $quantity, $operationKey, $expectedVersion);
    }

    public function removeLine(Cart $cart, int $lineId, string $operationKey, int $expectedVersion): Cart
    {
        if ($lineId <= 0 || trim($operationKey) === '') {
            throw new DomainException('Cart line removal identity is invalid.');
        }
        $requestHash = hash('sha256', $cart->getKey().'|remove|'.$lineId, true);

        return DB::transaction(function () use ($cart, $lineId, $operationKey, $expectedVersion, $requestHash): Cart {
            $operation = DB::table('cart_operations')->where('operation_key', $operationKey)->first();
            if ($operation !== null) {
                if (! hash_equals((string) $operation->request_hash, $requestHash)) {
                    throw new DomainException('Idempotency key was reused with a different Cart mutation.');
                }

                return Cart::query()->with('lines')->findOrFail((int) $operation->result_cart_id);
            }
            $locked = Cart::query()->whereKey($cart->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active' || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Cart is stale or inactive.');
            }
            $line = CartLine::query()->whereKey($lineId)->where('cart_id', $locked->getKey())->lockForUpdate()->firstOrFail();
            $line->delete();
            $locked->forceFill(['lock_version' => $expectedVersion + 1])->save();
            DB::table('cart_operations')->insert(['operation_key' => $operationKey, 'request_hash' => $requestHash, 'result_cart_id' => $locked->getKey(), 'created_at' => now()]);

            return $locked->refresh()->load('lines');
        }, 3);
    }

    public function mergeGuestIntoCustomer(string $guestToken, Customer $customer): Cart
    {
        $guest = Cart::query()->where('guest_token_hash', $this->tokenHash($guestToken))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->firstOrFail();
        if ($guest->status === 'merged') {
            return Cart::query()->with('lines')->findOrFail((int) $guest->merged_into_cart_id);
        }
        $target = $this->forCustomer($customer);
        if ($guest->getKey() === $target->getKey()) {
            return $target->load('lines');
        }

        return DB::transaction(function () use ($guest, $target): Cart {
            $carts = Cart::query()->whereKey([$guest->getKey(), $target->getKey()])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedGuest = $carts->get($guest->getKey());
            $lockedTarget = $carts->get($target->getKey());
            if (! $lockedGuest instanceof Cart || ! $lockedTarget instanceof Cart) {
                throw new DomainException('Cart merge identity is invalid.');
            }
            if ($lockedGuest->status === 'merged') {
                return Cart::query()->with('lines')->findOrFail($lockedGuest->merged_into_cart_id);
            }
            if ($lockedGuest->status !== 'active' || $lockedTarget->status !== 'active') {
                throw new DomainException('Only active Carts can merge.');
            }
            $sourceLines = CartLine::query()->where('cart_id', $lockedGuest->getKey())->orderBy('variant_id')->lockForUpdate()->get();
            foreach ($sourceLines as $source) {
                $destination = CartLine::query()->where('cart_id', $lockedTarget->getKey())->where('variant_id', $source->variant_id)->lockForUpdate()->first();
                $quantity = InventoryQuantity::from($source->quantity)->units + ($destination === null ? 0 : InventoryQuantity::from($destination->quantity)->units);
                if ($destination === null) {
                    CartLine::query()->create(['cart_id' => $lockedTarget->getKey(), 'variant_id' => $source->variant_id, 'quantity' => InventoryQuantity::fromUnits($quantity)->decimal()]);
                } else {
                    $destination->forceFill(['quantity' => InventoryQuantity::fromUnits($quantity)->decimal(), 'advisory_unit_amount' => null, 'advisory_line_amount' => null, 'advisory_available_qty' => null, 'advisory_status' => 'stale', 'previewed_at' => null, 'lock_version' => $destination->lock_version + 1])->save();
                }
            }
            $lockedGuest->lines()->delete();
            $lockedGuest->forceFill(['status' => 'merged', 'merged_into_cart_id' => $lockedTarget->getKey(), 'lock_version' => $lockedGuest->lock_version + 1])->save();
            $lockedTarget->forceFill(['lock_version' => $lockedTarget->lock_version + 1])->save();

            return $lockedTarget->refresh()->load('lines');
        }, 3);
    }

    public function preview(Cart $cart): Cart
    {
        if ($cart->status !== 'active') {
            throw new DomainException('Only an active Cart can be previewed.');
        }
        $lines = CartLine::query()->where('cart_id', $cart->getKey())->orderBy('id')->get();
        foreach ($lines as $line) {
            $variant = Variant::query()->whereKey($line->variant_id)->where('status', 'active')->first();
            if ($variant === null) {
                $line->forceFill(['advisory_status' => 'unavailable', 'previewed_at' => now(), 'lock_version' => $line->lock_version + 1])->save();

                continue;
            }
            $price = $this->pricing->resolve($variant, (string) $line->quantity, $cart->customer_id);
            $available = DB::table('stock_balances')->where('variant_id', $variant->getKey())
                ->selectRaw('COALESCE(SUM(on_hand_qty - reserved_qty), 0) AS available')->value('available');
            $line->forceFill([
                'advisory_unit_amount' => $price->unitAmount, 'advisory_line_amount' => $price->lineAmount,
                'advisory_available_qty' => InventoryQuantity::from((string) $available)->decimal(),
                'advisory_status' => 'fresh', 'previewed_at' => now(), 'lock_version' => $line->lock_version + 1,
            ])->save();
        }

        return $cart->refresh()->load('lines');
    }

    private function tokenHash(string $token): string
    {
        if (strlen($token) < 32) {
            throw new DomainException('Guest Cart token is invalid.');
        }

        return hash_hmac('sha256', $token, (string) config('app.key'), true);
    }
}
