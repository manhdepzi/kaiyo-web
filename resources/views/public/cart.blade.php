@extends('layouts.public')

@section('title', 'Giỏ hàng — Kaiyo')
@section('meta_description', 'Kiểm tra sản phẩm, số lượng, giá và tồn kho tham khảo trong giỏ hàng Kaiyo.')
@section('robots', 'noindex,nofollow')

@section('content')
<section class="mx-auto max-w-5xl px-5 py-12 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Mua hàng</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Giỏ hàng</h1>

    @if (session('status'))<x-ui.alert class="mt-6" tone="success">{{ session('status') }}</x-ui.alert>@endif
    @error('cart')<x-ui.alert class="mt-6" tone="warning" title="Giỏ hàng cần được kiểm tra lại">{{ $message }}</x-ui.alert>@enderror

    @if (count($cart->lines) === 0)
        <x-ui.empty-state class="mt-8" title="Giỏ hàng đang trống" description="Hãy tìm sản phẩm và chọn biến thể phù hợp để bắt đầu.">
            <x-ui.button :href="route('public.search')">Tìm sản phẩm</x-ui.button>
        </x-ui.empty-state>
    @else
        <div class="mt-8 space-y-4">
            @foreach ($cart->lines as $line)
                <article class="grid gap-5 rounded-panel border border-line bg-surface p-5 md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <h2 class="font-semibold"><a class="hover:text-brand hover:underline" href="{{ route('public.product', $line->productSlug) }}">{{ $line->productName }}</a></h2>
                        <p class="mt-1 text-sm text-ink-muted">{{ $line->variantName }} · SKU {{ $line->sku }}</p>
                        @if ($line->advisoryStatus === 'fresh' && $line->advisoryLineAmount !== null)
                            <p class="mt-3 font-semibold">{{ number_format($line->advisoryLineAmount, 0, ',', '.') }} ₫ <span class="text-sm font-normal text-ink-muted">· tham khảo</span></p>
                            <p class="mt-1 text-sm text-ink-muted">Khả dụng tham khảo: {{ $line->advisoryAvailableQuantity }}</p>
                        @elseif ($line->advisoryStatus === 'unavailable')
                            <x-ui.badge class="mt-3" tone="danger">Không còn khả dụng</x-ui.badge>
                        @else
                            <p class="mt-3 text-sm text-warning">Giá và tồn kho cần được làm mới.</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <form method="POST" action="{{ route('public.cart.lines.store') }}" class="flex items-end gap-2">
                            @csrf
                            <input type="hidden" name="variant_public_id" value="{{ $line->variantPublicId }}">
                            <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                            <input type="hidden" name="expected_version" value="{{ $cart->version }}">
                            <div>
                                <label class="block text-xs font-medium text-ink-muted" for="cart-quantity-{{ $line->id }}">Số lượng</label>
                                <input id="cart-quantity-{{ $line->id }}" name="quantity" value="{{ rtrim(rtrim($line->quantity, '0'), '.') }}" inputmode="decimal" required class="mt-1 min-h-9 w-24 rounded-control border border-line bg-surface px-2 text-ink">
                            </div>
                            <x-ui.button type="submit" variant="secondary" size="sm">Cập nhật</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('public.cart.lines.destroy', $line->id) }}">
                            @csrf @method('DELETE')
                            <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                            <input type="hidden" name="expected_version" value="{{ $cart->version }}">
                            <x-ui.button type="submit" variant="ghost" size="sm">Xóa</x-ui.button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-8 flex flex-wrap justify-between gap-3 border-t border-line pt-6">
            <form method="POST" action="{{ route('public.cart.refresh') }}">@csrf<x-ui.button type="submit" variant="secondary">Làm mới giá và tồn kho</x-ui.button></form>
            @auth
                <x-ui.button :href="route('public.checkout')">Tiến hành thanh toán</x-ui.button>
            @else
                <x-ui.button :href="route('login')">Đăng nhập để checkout</x-ui.button>
            @endauth
        </div>
        <p class="mt-4 text-sm text-ink-muted">Giá và tồn kho trong giỏ chỉ mang tính tham khảo; checkout luôn tính lại từ nguồn dữ liệu chính thức.</p>
    @endif
</section>
@endsection
