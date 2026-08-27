<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Cart\Application\CartContext;
use App\Modules\Cart\Application\CartReader;
use App\Modules\Cart\Application\CartService;
use App\Modules\Cart\Application\Data\ResolvedCart;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class PublicCartController extends Controller
{
    private const COOKIE = 'kaiyo_cart';

    public function show(Request $request, CartContext $context, CartReader $reader): View
    {
        $resolved = $this->resolve($request, $context);

        return view('public.cart', ['cart' => $reader->read($resolved->cart)]);
    }

    public function storeLine(Request $request, CartContext $context, CartService $carts): RedirectResponse
    {
        $validated = $request->validate([
            'variant_public_id' => ['required', 'string', 'size:26', 'regex:/^[0-9A-HJKMNP-TV-Z]+$/'],
            'quantity' => ['required', 'regex:/^\d{1,16}(?:\.\d{1,4})?$/'],
            'operation_key' => ['required', 'string', 'max:100'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
        ]);
        $resolved = $this->resolve($request, $context);

        try {
            $carts->putPublicLine(
                $resolved->cart,
                (string) $validated['variant_public_id'],
                (string) $validated['quantity'],
                (string) $validated['operation_key'],
                isset($validated['expected_version']) ? (int) $validated['expected_version'] : $resolved->cart->lock_version,
            );
        } catch (DomainException $exception) {
            return to_route('public.cart')->withErrors(['cart' => $exception->getMessage()]);
        }

        return to_route('public.cart')->with('status', 'Giỏ hàng đã được cập nhật.');
    }

    public function removeLine(int $line, Request $request, CartContext $context, CartService $carts): RedirectResponse
    {
        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:100'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $resolved = $this->resolve($request, $context);

        try {
            $carts->removeLine($resolved->cart, $line, (string) $validated['operation_key'], (int) $validated['expected_version']);
        } catch (DomainException $exception) {
            return to_route('public.cart')->withErrors(['cart' => $exception->getMessage()]);
        }

        return to_route('public.cart')->with('status', 'Sản phẩm đã được xóa khỏi giỏ hàng.');
    }

    public function refresh(Request $request, CartContext $context, CartService $carts): RedirectResponse
    {
        $resolved = $this->resolve($request, $context);
        try {
            $carts->preview($resolved->cart);
        } catch (DomainException $exception) {
            return to_route('public.cart')->withErrors(['cart' => 'Chưa thể làm mới giá và tồn kho: '.$exception->getMessage()]);
        }

        return to_route('public.cart')->with('status', 'Giá và tồn kho tham khảo đã được làm mới.');
    }

    private function resolve(Request $request, CartContext $context): ResolvedCart
    {
        $account = $request->user();
        $cookie = $request->cookie(self::COOKIE);
        $resolved = $context->resolve(is_string($cookie) ? $cookie : null, $account instanceof UserAccount ? $account : null);
        if ($resolved->newGuestToken !== null) {
            Cookie::queue(cookie(
                self::COOKIE,
                $resolved->newGuestToken,
                60 * 24 * 30,
                '/',
                null,
                (bool) config('session.secure', false),
                true,
                false,
                'lax',
            ));
        }

        return $resolved;
    }
}
