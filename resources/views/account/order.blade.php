@extends('layouts.public')

@section('title', 'Đơn hàng — Kaiyo')
@section('meta_description', 'Chi tiết và trạng thái đơn hàng Kaiyo của bạn.')

@section('content')
<section class="mx-auto max-w-5xl px-5 py-12 lg:px-8">
    <a href="{{ route('account') }}" class="text-sm font-semibold text-brand hover:underline">← Quay lại tài khoản</a>
    <div class="mt-5 flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm text-ink-muted">Đơn hàng</p><h1 class="mt-1 text-3xl font-bold">{{ $order->publicId }}</h1></div><x-ui.badge tone="success">{{ $order->state }}</x-ui.badge></div>
    <div class="mt-8 grid gap-5 sm:grid-cols-3">
        <x-ui.card title="Thanh toán"><p class="font-semibold">{{ $order->paymentState ?? 'Chưa khởi tạo' }}</p><p class="mt-1 text-sm text-ink-muted">{{ $order->paymentMethod }}</p></x-ui.card>
        <x-ui.card title="Giao hàng"><p class="font-semibold">{{ $order->shipmentState ?? 'Chưa khởi tạo' }}</p><p class="mt-1 text-sm text-ink-muted">Mã vận đơn không được hiển thị từ dữ liệu băm.</p></x-ui.card>
        <x-ui.card title="Yêu cầu hủy"><p class="font-semibold">{{ $order->cancellationState ?? 'Chưa có' }}</p></x-ui.card>
    </div>
    <x-ui.card class="mt-6" title="Sản phẩm">
        <ul class="divide-y divide-line">@foreach($order->lines as $line)<li class="flex justify-between gap-5 py-4"><span><strong>{{ $line['name'] }}</strong><small class="mt-1 block text-ink-muted">SKU {{ $line['sku'] }} · {{ $line['quantity'] }}</small></span><span class="font-semibold">{{ number_format($line['line_amount'], 0, ',', '.') }} ₫</span></li>@endforeach</ul>
        <dl class="ml-auto mt-5 grid max-w-sm grid-cols-2 gap-2 text-sm"><dt>Hàng hóa</dt><dd class="text-right">{{ number_format($order->merchandiseAmount, 0, ',', '.') }} ₫</dd><dt>VAT</dt><dd class="text-right">{{ number_format($order->taxAmount, 0, ',', '.') }} ₫</dd><dt>Giao hàng</dt><dd class="text-right">{{ number_format($order->shippingAmount, 0, ',', '.') }} ₫</dd><dt class="font-bold">Tổng</dt><dd class="text-right text-lg font-bold">{{ number_format($order->finalAmount, 0, ',', '.') }} ₫</dd></dl>
    </x-ui.card>
    <x-ui.card class="mt-6" title="Lịch sử trạng thái"><ol class="space-y-3">@forelse($order->history as $event)<li class="border-l-2 border-brand pl-4"><p class="font-semibold">{{ $event['to'] }}</p><p class="text-sm text-ink-muted">{{ $event['occurred_at'] }}</p></li>@empty<li class="text-sm text-ink-muted">Chưa có lịch sử.</li>@endforelse</ol></x-ui.card>
    @if(in_array($order->state, ['pending', 'confirmed'], true) && $order->cancellationState === null)<x-ui.alert class="mt-6" tone="info" title="Yêu cầu hủy">Tính năng gửi yêu cầu hủy sẽ mở khi entitlement `orders.cancel_request` cho Customer được cấu hình; hệ thống không tự cấp quyền.</x-ui.alert>@endif
</section>
@endsection
