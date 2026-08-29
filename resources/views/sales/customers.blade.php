@extends('layouts.staff')

@section('title', 'Khách hàng — Kaiyo Sales')

@section('content')
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div><p class="text-sm font-semibold uppercase tracking-widest text-brand">CRM</p><h1 class="mt-2 text-3xl font-bold">Khách hàng</h1><p class="mt-2 text-ink-muted">Danh sách được phân trang và lọc hoàn toàn ở máy chủ.</p></div>
        <dl class="flex flex-wrap gap-3 text-sm">
            @forelse ($directory->statusCounts as $state => $count)
                <div class="min-w-28 rounded-control border border-line bg-surface px-4 py-3"><dt class="text-ink-muted">{{ $state }}</dt><dd class="mt-1 text-xl font-bold">{{ number_format($count) }}</dd></div>
            @empty
                <div class="rounded-control border border-line bg-surface px-4 py-3 text-ink-muted">Chưa có dữ liệu</div>
            @endforelse
        </dl>
    </div>

    <x-ui.card class="mt-8" title="Tìm và lọc" description="Tìm theo tiền tố tên, email chuẩn hóa hoặc số điện thoại.">
        <form method="GET" action="{{ route('sales.customers') }}" class="grid gap-4 md:grid-cols-[1fr_14rem_auto] md:items-end">
            <x-ui.input name="q" label="Từ khóa" :value="$directory->query" autocomplete="off" />
            <div><label for="customer-status" class="block text-sm font-medium">Trạng thái</label><select id="customer-status" name="status" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3 py-2"><option value="">Tất cả</option><option value="active" @selected($directory->status === 'active')>Đang hoạt động</option><option value="inactive" @selected($directory->status === 'inactive')>Không hoạt động</option></select></div>
            <x-ui.button type="submit" icon="funnel">Áp dụng</x-ui.button>
        </form>
    </x-ui.card>

    <div class="mt-6 overflow-hidden rounded-panel border border-line bg-surface shadow-panel">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-left text-sm">
                <caption class="sr-only">Danh sách khách hàng CRM</caption>
                <thead class="bg-surface-muted text-ink-muted"><tr><th class="px-5 py-3 font-semibold">Khách hàng</th><th class="px-5 py-3 font-semibold">Liên hệ</th><th class="px-5 py-3 font-semibold">Trạng thái</th><th class="px-5 py-3 font-semibold">Cập nhật</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($directory->customers as $customer)
                        <tr><td class="px-5 py-4"><a href="{{ route('sales.customers.show', $customer['public_id']) }}" class="font-semibold text-brand hover:underline">{{ $customer['display_name'] }}</a><span class="mt-1 block font-mono text-xs text-ink-muted">{{ $customer['public_id'] }}</span></td><td class="px-5 py-4"><span class="block">{{ $customer['email'] ?? 'Chưa có email' }}</span><span class="mt-1 block text-ink-muted">{{ $customer['phone'] ?? 'Chưa có số điện thoại' }}</span></td><td class="px-5 py-4"><x-ui.badge :tone="$customer['status'] === 'active' ? 'success' : 'neutral'">{{ $customer['status'] }}</x-ui.badge></td><td class="px-5 py-4 text-ink-muted"><time datetime="{{ $customer['updated_at'] }}">{{ $customer['updated_at'] }}</time></td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10"><x-ui.empty-state title="Không tìm thấy khách hàng" description="Thử đổi từ khóa hoặc trạng thái lọc." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($directory->previousCursor || $directory->nextCursor)
        <nav class="mt-6 flex justify-between gap-3" aria-label="Phân trang khách hàng">
            @if ($directory->previousCursor)<x-ui.button :href="route('sales.customers', array_filter(['q' => $directory->query, 'status' => $directory->status, 'cursor' => $directory->previousCursor]))" variant="secondary">Trang trước</x-ui.button>@else<span></span>@endif
            @if ($directory->nextCursor)<x-ui.button :href="route('sales.customers', array_filter(['q' => $directory->query, 'status' => $directory->status, 'cursor' => $directory->nextCursor]))" variant="secondary">Trang sau</x-ui.button>@endif
        </nav>
    @endif
</section>
@endsection
