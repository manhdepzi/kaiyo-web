@extends('layouts.public')

@section('title', 'Tài khoản — Kaiyo')
@section('meta_description', 'Quản lý hồ sơ, đơn hàng, báo giá, công ty và bảo mật tài khoản Kaiyo.')

@section('content')
<section class="mx-auto max-w-6xl px-5 py-12 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-sm font-semibold uppercase tracking-widest text-brand">Customer Portal</p><h1 class="mt-3 text-3xl font-bold">Tài khoản của bạn</h1><p class="mt-2 text-ink-muted">{{ $portal->accountEmail }}</p></div>
        <div class="flex gap-3"><x-ui.button :href="route('account.security')" variant="secondary">Bảo mật</x-ui.button><form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="ghost">Đăng xuất</x-ui.button></form></div>
    </div>
    @if (session('status'))<x-ui.alert class="mt-6" tone="success">{{ session('status') }}</x-ui.alert>@endif
    @error('profile')<x-ui.alert class="mt-6" tone="danger" title="Hồ sơ cần được kiểm tra">{{ $message }}</x-ui.alert>@enderror

    @if ($portal->customer === null)
        <x-ui.card class="mt-8" title="Hoàn tất hồ sơ khách hàng">
            <p class="text-sm text-ink-muted">Hồ sơ Customer liên kết email đã xác minh với giỏ hàng, Checkout, đơn hàng và báo giá. Email đã thuộc CRM khác sẽ không được tự liên kết.</p>
            <form method="POST" action="{{ route('account.profile.provision') }}" class="mt-5 flex max-w-xl flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1"><x-ui.input name="display_name" label="Tên hiển thị" :value="old('display_name')" required /></div>
                <x-ui.button type="submit">Tạo hồ sơ</x-ui.button>
            </form>
        </x-ui.card>
    @else
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            <x-ui.card title="Hồ sơ"><p class="font-semibold">{{ $portal->customer['display_name'] }}</p><p class="mt-2 text-sm text-ink-muted">{{ $portal->customer['email'] ?? 'Chưa có email hồ sơ' }}</p><x-ui.badge class="mt-3" tone="success">{{ $portal->customer['status'] }}</x-ui.badge></x-ui.card>
            <x-ui.card title="Đơn hàng"><p class="text-3xl font-bold">{{ count($portal->orders) }}</p><p class="mt-2 text-sm text-ink-muted">Tối đa 10 đơn gần nhất</p></x-ui.card>
            <x-ui.card title="Báo giá"><p class="text-3xl font-bold">{{ count($portal->quotes) }}</p><p class="mt-2 text-sm text-ink-muted">Tối đa 10 yêu cầu gần nhất</p></x-ui.card>
        </div>
        <x-ui.card class="mt-8" title="Chỉnh sửa hồ sơ">
            <form method="POST" action="{{ route('account.profile.update') }}" class="flex max-w-xl flex-col gap-3 sm:flex-row sm:items-end">
                @csrf @method('PATCH')
                <input type="hidden" name="expected_version" value="{{ $portal->customer['version'] }}">
                <div class="flex-1"><x-ui.input name="display_name" label="Tên hiển thị" :value="old('display_name', $portal->customer['display_name'])" required /></div>
                <x-ui.button type="submit">Lưu thay đổi</x-ui.button>
            </form>
        </x-ui.card>
        <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <section aria-labelledby="orders-heading"><h2 id="orders-heading" class="text-xl font-bold">Đơn hàng gần đây</h2><div class="mt-4 space-y-3">
                @forelse ($portal->orders as $order)<a href="{{ route('account.orders.show', $order['public_id']) }}" class="block rounded-panel border border-line bg-surface p-4 hover:border-brand"><span class="font-semibold">{{ $order['public_id'] }}</span><span class="mt-1 block text-sm text-ink-muted">{{ $order['state'] }} · {{ number_format($order['final_amount'], 0, ',', '.') }} ₫</span></a>@empty <x-ui.empty-state title="Chưa có đơn hàng" description="Đơn hàng đã đặt sẽ xuất hiện tại đây." /> @endforelse
            </div></section>
            <section aria-labelledby="quotes-heading"><h2 id="quotes-heading" class="text-xl font-bold">Báo giá gần đây</h2><div class="mt-4 space-y-3">
                @forelse ($portal->quotes as $quote)<a href="{{ route('public.quotation.view', $quote['public_id']) }}" class="block rounded-panel border border-line bg-surface p-4 hover:border-brand"><span class="font-semibold">{{ $quote['public_id'] }}</span><span class="mt-1 block text-sm text-ink-muted">{{ $quote['state'] }} · phiên bản {{ $quote['revision'] }} · {{ number_format($quote['final_amount'], 0, ',', '.') }} ₫</span></a>@empty <x-ui.empty-state title="Chưa có báo giá" description="Yêu cầu báo giá đã gửi sẽ xuất hiện tại đây." /> @endforelse
            </div></section>
        </div>
        <section class="mt-8" aria-labelledby="companies-heading"><h2 id="companies-heading" class="text-xl font-bold">Công ty</h2><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($portal->companies as $company)<x-ui.card :title="$company['display_name']"><p class="text-sm text-ink-muted">Thành viên đang hoạt động</p></x-ui.card>@empty <p class="text-sm text-ink-muted">Chưa có quan hệ công ty đang hoạt động.</p> @endforelse
        </div></section>
    @endif
</section>
@endsection
