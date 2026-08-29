@extends('layouts.staff')

@section('title', $customer->displayName.' — Kaiyo Sales')

@section('content')
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-8">
    <a href="{{ route('sales.customers') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:underline"><x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />Danh sách khách hàng</a>
    <div class="mt-5 flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-widest text-brand">Customer 360</p><h1 class="mt-2 text-3xl font-bold">{{ $customer->displayName }}</h1><p class="mt-2 font-mono text-xs text-ink-muted">{{ $customer->publicId }}</p></div><x-ui.badge :tone="$customer->status === 'active' ? 'success' : 'neutral'">{{ $customer->status }}</x-ui.badge></div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Hồ sơ"><dl class="space-y-3 text-sm"><div><dt class="text-ink-muted">Email</dt><dd class="mt-1 font-medium">{{ $customer->email ?? 'Chưa có' }}</dd></div><div><dt class="text-ink-muted">Điện thoại</dt><dd class="mt-1 font-medium">{{ $customer->phone ?? 'Chưa có' }}</dd></div><div><dt class="text-ink-muted">Phụ trách</dt><dd class="mt-1 font-medium">{{ $customer->ownerEmail ?? 'Chưa phân công' }}</dd></div></dl></x-ui.card>
        <x-ui.card class="lg:col-span-2" title="Liên hệ"><div class="grid gap-3 sm:grid-cols-2">@forelse($customer->contacts as $contact)<article class="rounded-control border border-line bg-surface-muted p-4 text-sm"><strong>{{ $contact['name'] }}</strong><p class="mt-2">{{ $contact['email'] ?? 'Chưa có email' }}</p><p class="text-ink-muted">{{ $contact['phone'] ?? 'Chưa có số điện thoại' }}</p></article>@empty<x-ui.empty-state title="Chưa có liên hệ" description="Liên hệ thuộc khách hàng sẽ xuất hiện tại đây." />@endforelse</div></x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Đơn hàng gần đây">
            @if (! $customer->canReadOrders)<x-ui.alert tone="warning" title="Không có quyền">Cần entitlement `orders.read` trên khách hàng này.</x-ui.alert>@else<div class="space-y-3">@forelse($customer->orders as $order)<article class="flex justify-between gap-4 rounded-control border border-line p-4 text-sm"><span><strong>{{ $order['public_id'] }}</strong><small class="mt-1 block text-ink-muted">{{ $order['state'] }}</small></span><strong>{{ number_format($order['final_amount'], 0, ',', '.') }} ₫</strong></article>@empty<x-ui.empty-state title="Chưa có đơn hàng" description="Không có đơn hàng thuộc khách hàng này." />@endforelse</div>@endif
        </x-ui.card>
        <x-ui.card title="Báo giá gần đây">
            @if (! $customer->canReadQuotes)<x-ui.alert tone="warning" title="Không có quyền">Cần entitlement `quotes.read` trên khách hàng này.</x-ui.alert>@else<div class="space-y-3">@forelse($customer->quotes as $quote)<article class="flex justify-between gap-4 rounded-control border border-line p-4 text-sm"><span><strong>{{ $quote['public_id'] }}</strong><small class="mt-1 block text-ink-muted">{{ $quote['state'] }} · revision {{ $quote['revision'] }}</small></span><strong>{{ number_format($quote['final_amount'], 0, ',', '.') }} ₫</strong></article>@empty<x-ui.empty-state title="Chưa có báo giá" description="Không có báo giá thuộc khách hàng này." />@endforelse</div>@endif
        </x-ui.card>
    </div>
</section>
@endsection
