@extends('layouts.public')
@section('robots', 'noindex,nofollow')

@section('title', 'Đặt hàng thành công — Kaiyo')
@section('meta_description', 'Xác nhận đơn hàng Kaiyo đã được ghi nhận.')

@section('content')
<section class="mx-auto max-w-3xl px-5 py-16 lg:px-8">
    <x-ui.alert tone="success" title="Đơn hàng đã được ghi nhận">Mã đơn hàng: {{ $order['publicId'] }}</x-ui.alert>
    <x-ui.card class="mt-6" title="Thông tin thanh toán">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-ink-muted">Trạng thái</dt><dd class="mt-1 font-semibold">{{ $order['state'] }}</dd></div>
            <div><dt class="text-sm text-ink-muted">Phương thức</dt><dd class="mt-1 font-semibold">{{ $order['paymentMethod'] }}</dd></div>
            <div><dt class="text-sm text-ink-muted">Tổng thanh toán</dt><dd class="mt-1 text-xl font-bold">{{ number_format($order['finalAmount'], 0, ',', '.') }} ₫</dd></div>
        </dl>
    </x-ui.card>
    <div class="mt-8 flex flex-wrap gap-3"><x-ui.button :href="route('account')">Xem tài khoản</x-ui.button><x-ui.button :href="route('public.search')" variant="secondary">Tiếp tục mua hàng</x-ui.button></div>
</section>
@endsection
