@extends('layouts.public')

@section('title', 'Tài khoản — Kaiyo')
@section('meta_description', 'Quản lý hồ sơ, đơn hàng, báo giá, công ty và bảo mật tài khoản Kaiyo.')

@section('content')
<section class="mx-auto max-w-6xl px-5 py-12 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-sm font-semibold uppercase tracking-widest text-brand">Customer Portal</p><h1 class="mt-3 text-3xl font-bold">Tài khoản của bạn</h1><p class="mt-2 text-ink-muted">{{ $portal->accountEmail }}</p></div>
        <div class="flex gap-3"><x-ui.button :href="route('account.security')" variant="secondary" icon="lock-closed">Bảo mật</x-ui.button><form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle">Đăng xuất</x-ui.button></form></div>
    </div>
    @if (session('status'))<x-ui.alert class="mt-6" tone="success">{{ session('status') }}</x-ui.alert>@endif
    @error('profile')<x-ui.alert class="mt-6" tone="danger" title="Hồ sơ cần được kiểm tra">{{ $message }}</x-ui.alert>@enderror

    @if ($portal->customer === null)
        <x-ui.card class="mt-8" title="Hoàn tất hồ sơ khách hàng">
            <p class="text-sm text-ink-muted">Hồ sơ Customer liên kết email đã xác minh với giỏ hàng, Checkout, đơn hàng và báo giá. Email đã thuộc CRM khác sẽ không được tự liên kết.</p>
            <form method="POST" action="{{ route('account.profile.provision') }}" class="mt-5 flex max-w-xl flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1"><x-ui.input name="display_name" label="Tên hiển thị" :value="old('display_name')" required /></div>
                <x-ui.button type="submit" icon="user-plus">Tạo hồ sơ</x-ui.button>
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
                <x-ui.button type="submit" icon="check">Lưu thay đổi</x-ui.button>
            </form>
        </x-ui.card>
        @include('account._addresses')
        <section class="mt-8" aria-labelledby="wishlist-heading">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 id="wishlist-heading" class="text-xl font-bold">Sản phẩm yêu thích</h2><p class="mt-1 text-sm text-ink-muted">Danh sách sản phẩm đang công bố mà bạn muốn xem lại.</p></div><x-ui.badge tone="info">{{ count($portal->wishlist) }}/100 sản phẩm</x-ui.badge></div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($portal->wishlist as $item)
                    <article class="rounded-panel border border-line bg-surface p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ $item['category'] }}</p>
                        <h3 class="mt-2 font-bold"><a class="hover:text-brand" href="{{ route('public.product', $item['slug']) }}">{{ $item['name'] }}</a></h3>
                        <div class="mt-4 flex flex-wrap gap-2"><x-ui.button :href="route('public.product', $item['slug'])" variant="ghost" size="sm" icon="eye">Xem sản phẩm</x-ui.button><form method="POST" action="{{ route('account.wishlist.destroy', $item['public_id']) }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="secondary" size="sm" icon="trash">Bỏ lưu</x-ui.button></form></div>
                    </article>
                @empty
                    <x-ui.empty-state title="Chưa có sản phẩm yêu thích" description="Bạn có thể lưu sản phẩm ngay tại trang chi tiết để quay lại nhanh hơn." />
                @endforelse
            </div>
        </section>
        <section class="mt-8" aria-labelledby="own-reviews-heading">
            <h2 id="own-reviews-heading" class="text-xl font-bold">Đánh giá của bạn</h2><p class="mt-1 text-sm text-ink-muted">Theo dõi trạng thái kiểm duyệt của đánh giá mua hàng đã xác minh.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($portal->reviews as $review)
                    <article class="rounded-panel border border-line bg-surface p-5"><div class="flex items-start justify-between gap-3"><p class="font-bold text-warning">{{ $review['rating'] }}/5 ★</p><x-ui.badge :tone="$review['status'] === 'approved' ? 'success' : ($review['status'] === 'rejected' ? 'danger' : 'warning')">{{ $review['status'] }}</x-ui.badge></div><h3 class="mt-2 font-bold">{{ $review['title'] }}</h3><p class="mt-1 text-sm text-ink-muted">{{ $review['product_name'] }}</p><x-ui.button :href="route('public.product', $review['product_slug'])" class="mt-3" variant="ghost" size="sm" icon="eye">Xem sản phẩm</x-ui.button></article>
                @empty
                    <x-ui.empty-state title="Chưa có đánh giá" description="Sau khi đơn được giao, bạn có thể đánh giá sản phẩm tại trang chi tiết." />
                @endforelse
            </div>
        </section>
        <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <section aria-labelledby="orders-heading"><h2 id="orders-heading" class="text-xl font-bold">Đơn hàng gần đây</h2><div class="mt-4 space-y-3">
                @forelse ($portal->orders as $order)<a href="{{ route('account.orders.show', $order['public_id']) }}" class="block rounded-panel border border-line bg-surface p-4 hover:border-brand"><span class="font-semibold">{{ $order['public_id'] }}</span><span class="mt-1 block text-sm text-ink-muted">{{ $order['state'] }} · {{ number_format($order['final_amount'], 0, ',', '.') }} ₫</span></a>@empty <x-ui.empty-state title="Chưa có đơn hàng" description="Đơn hàng đã đặt sẽ xuất hiện tại đây." /> @endforelse
            </div></section>
            <section aria-labelledby="quotes-heading"><h2 id="quotes-heading" class="text-xl font-bold">Báo giá gần đây</h2><div class="mt-4 space-y-3">
                @forelse ($portal->quotes as $quote)<a href="{{ route('public.quotation.view', $quote['public_id']) }}" class="block rounded-panel border border-line bg-surface p-4 hover:border-brand"><span class="font-semibold">{{ $quote['public_id'] }}</span><span class="mt-1 block text-sm text-ink-muted">{{ $quote['state'] }} · phiên bản {{ $quote['revision'] }} · {{ number_format($quote['final_amount'], 0, ',', '.') }} ₫</span></a>@empty <x-ui.empty-state title="Chưa có báo giá" description="Yêu cầu báo giá đã gửi sẽ xuất hiện tại đây." /> @endforelse
            </div></section>
        </div>
        <section class="mt-8" aria-labelledby="notifications-heading">
            <div class="flex items-center gap-3"><x-heroicon-o-bell class="size-6 text-brand" aria-hidden="true" /><h2 id="notifications-heading" class="text-xl font-bold">Thông báo đơn hàng</h2></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($portal->notifications as $notification)
                    <article class="rounded-panel border bg-surface p-4 {{ $notification['is_read'] ? 'border-line' : 'border-brand/40 shadow-sm' }}">
                        <span class="flex items-start justify-between gap-3"><span class="font-semibold">{{ $notification['title'] }}</span>@unless ($notification['is_read'])<x-ui.badge tone="info">Mới</x-ui.badge>@endunless</span>
                        <span class="mt-2 block text-sm text-ink-muted">{{ $notification['order_public_id'] }} · {{ $notification['to_state'] }}</span>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-ui.button :href="route('account.orders.show', $notification['order_public_id'])" variant="ghost" size="sm" icon="eye">Xem đơn hàng</x-ui.button>
                            @unless ($notification['is_read'])
                                <form method="POST" action="{{ route('account.notifications.read', $notification['public_id']) }}">@csrf @method('PATCH')<x-ui.button type="submit" variant="secondary" size="sm" icon="check">Đã đọc</x-ui.button></form>
                            @endunless
                        </div>
                    </article>
                @empty
                    <x-ui.empty-state title="Chưa có thông báo" description="Các thay đổi quan trọng của đơn hàng sẽ xuất hiện tại đây." />
                @endforelse
            </div>
        </section>
        <x-ui.card class="mt-8" title="Tùy chọn thông báo đơn hàng" description="Thông báo trong ứng dụng luôn bật. Email/SMS chỉ được gửi khi kênh nhà cung cấp tương ứng đã được cấu hình và vận hành.">
            <form method="POST" action="{{ route('account.notification-preferences.update') }}" class="grid gap-4 sm:grid-cols-3 sm:items-end">
                @csrf @method('PATCH')
                <input type="hidden" name="expected_version" value="{{ $portal->notificationPreferences['version'] }}">
                <label class="flex min-h-12 items-center gap-3 rounded-control border border-line bg-surface-muted px-4"><input type="checkbox" checked disabled class="size-4"><span><strong class="block">Trong ứng dụng</strong><small class="text-ink-muted">Thông báo giao dịch bắt buộc</small></span></label>
                <label class="flex min-h-12 items-center gap-3 rounded-control border border-line px-4"><input type="checkbox" name="email" value="1" class="size-4" @checked($portal->notificationPreferences['email'])><span><strong class="block">Email</strong><small class="text-ink-muted">Khi provider được bật</small></span></label>
                <label class="flex min-h-12 items-center gap-3 rounded-control border border-line px-4"><input type="checkbox" name="sms" value="1" class="size-4" @checked($portal->notificationPreferences['sms'])><span><strong class="block">SMS</strong><small class="text-ink-muted">Cần số điện thoại hồ sơ</small></span></label>
                @error('preferences')<p class="text-sm text-danger sm:col-span-3">{{ $message }}</p>@enderror
                <div class="sm:col-span-3"><x-ui.button type="submit" icon="bell">Lưu tùy chọn</x-ui.button></div>
            </form>
        </x-ui.card>
        <section class="mt-8" aria-labelledby="companies-heading"><h2 id="companies-heading" class="text-xl font-bold">Công ty</h2><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($portal->companies as $company)
                <x-ui.card :title="$company['display_name']">
                    <p class="text-sm text-ink-muted">Thành viên đang hoạt động</p>
                    @if($company['capabilities'] !== [])
                        <ul class="mt-3 flex flex-wrap gap-2" aria-label="Quyền trong công ty">
                            @foreach($company['capabilities'] as $capability)<li><x-ui.badge tone="info">{{ $capability }}</x-ui.badge></li>@endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-warning">Membership chưa được cấp capability thương mại.</p>
                    @endif
                </x-ui.card>
            @empty <p class="text-sm text-ink-muted">Chưa có quan hệ công ty đang hoạt động.</p> @endforelse
        </div></section>
    @endif
</section>
@endsection
