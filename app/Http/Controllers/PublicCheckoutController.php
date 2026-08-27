<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Cart\Application\CartContext;
use App\Modules\Cart\Application\CartReader;
use App\Modules\Cart\Application\Data\ResolvedCart;
use App\Modules\Checkout\Application\Actions\PlaceCheckoutOrder;
use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\CheckoutCommand;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class PublicCheckoutController extends Controller
{
    private const CART_COOKIE = 'kaiyo_cart';

    public function show(Request $request, CartContext $context, CartReader $reader): View
    {
        $resolved = $this->resolveCart($request, $context);

        return view('public.checkout', [
            'cart' => $reader->read($resolved->cart),
            'customerLinked' => $this->customer($request) !== null,
            'shippingMethods' => $this->shippingMethods(),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function place(Request $request, CartContext $context, PlaceCheckoutOrder $checkout): RedirectResponse
    {
        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line_1' => ['required', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'locality' => ['nullable', 'string', 'max:160'],
            'subdivision' => ['nullable', 'string', 'max:160'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'in:VN'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'shipping_method' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'in:cod,bank_transfer,online_gateway'],
            'invoice_requested' => ['nullable', 'boolean'],
        ]);

        $customer = $this->customer($request);
        if ($customer === null) {
            return to_route('public.checkout')->withErrors([
                'checkout' => 'Tài khoản chưa có hồ sơ khách hàng đang hoạt động. Vui lòng hoàn tất hồ sơ trước khi đặt hàng.',
            ]);
        }
        if (! in_array((string) $validated['shipping_method'], array_keys($this->shippingMethods()), true)) {
            return to_route('public.checkout')->withInput()->withErrors([
                'shipping_method' => 'Phương thức giao hàng chưa được cấu hình hoặc đã bị tắt.',
            ]);
        }
        if (! array_key_exists((string) $validated['payment_method'], $this->paymentMethods())) {
            return to_route('public.checkout')->withInput()->withErrors([
                'payment_method' => 'Phương thức thanh toán chưa được kích hoạt.',
            ]);
        }

        $resolved = $this->resolveCart($request, $context);
        if ((int) $resolved->cart->customer_id !== (int) $customer->getKey()) {
            return to_route('public.checkout')->withErrors(['checkout' => 'Không thể xác minh chủ sở hữu giỏ hàng.']);
        }

        try {
            $address = new AddressData(
                recipientName: trim((string) $validated['recipient_name']),
                addressLine1: trim((string) $validated['address_line_1']),
                countryCode: (string) $validated['country_code'],
                companyName: $this->optional($validated['company_name'] ?? null),
                taxCode: $this->optional($validated['tax_code'] ?? null),
                addressLine2: $this->optional($validated['address_line_2'] ?? null),
                locality: $this->optional($validated['locality'] ?? null),
                subdivision: $this->optional($validated['subdivision'] ?? null),
                postalCode: $this->optional($validated['postal_code'] ?? null),
                phone: $this->optional($validated['phone'] ?? null),
            );
            $result = $checkout->execute(new CheckoutCommand(
                cart: $resolved->cart,
                operationKey: (string) $validated['operation_key'],
                shippingAddress: $address,
                billingAddress: $address,
                shippingMethod: (string) $validated['shipping_method'],
                paymentMethod: (string) $validated['payment_method'],
                invoiceRequested: (bool) ($validated['invoice_requested'] ?? false),
            ));
        } catch (DomainException $exception) {
            report($exception);

            return to_route('public.checkout')->withInput()->withErrors([
                'checkout' => 'Chưa thể đặt hàng: '.$exception->getMessage(),
            ]);
        }

        return to_route('public.checkout.complete', $result->order->public_id);
    }

    /** @return array<string, string> */
    private function shippingMethods(): array
    {
        $configured = config('shipping.methods');
        if (! is_array($configured)) {
            return [];
        }

        $result = [];
        foreach ($configured as $code => $details) {
            if (is_string($code) && is_array($details) && ($details['enabled'] ?? false) === true) {
                $label = $details['label'] ?? $code;
                $result[$code] = is_string($label) ? $label : $code;
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    private function paymentMethods(): array
    {
        $methods = ['cod' => 'Thanh toán khi nhận hàng (COD)', 'bank_transfer' => 'Chuyển khoản ngân hàng'];
        if (config('payment.online_gateway.enabled') === true) {
            $methods['online_gateway'] = 'Thanh toán trực tuyến';
        }

        return $methods;
    }

    private function resolveCart(Request $request, CartContext $context): ResolvedCart
    {
        $account = $request->user();
        $cookie = $request->cookie(self::CART_COOKIE);
        $resolved = $context->resolve(is_string($cookie) ? $cookie : null, $account instanceof UserAccount ? $account : null);
        if ($resolved->newGuestToken !== null) {
            Cookie::queue(cookie(self::CART_COOKIE, $resolved->newGuestToken, 60 * 24 * 30, '/', null, (bool) config('session.secure', false), true, false, 'lax'));
        }

        return $resolved;
    }

    private function customer(Request $request): ?Customer
    {
        $account = $request->user();
        if (! $account instanceof UserAccount) {
            return null;
        }

        return Customer::query()->where('user_account_id', $account->getKey())->where('status', 'active')->first();
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
