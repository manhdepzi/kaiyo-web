@extends('layouts.admin')

@section('title', 'Merchant — Kaiyo')

@section('content')
<div class="mx-auto max-w-7xl px-5 py-10 lg:px-8">
    <h1 class="text-3xl font-bold">Merchant feed</h1>
    <p class="mt-2 text-ink-muted">Batch dùng dữ liệu Catalog, Pricing và Inventory authoritative. Provider thật mặc định bị vô hiệu hóa.</p>
    @if(session('status'))<x-ui.alert class="mt-5" tone="success" :title="session('status')" />@endif
    <div class="mt-8 grid gap-8 lg:grid-cols-[22rem_1fr]">
        <x-ui.card title="Tạo batch">
            <form method="POST" action="{{ route('admin.merchant.store') }}" class="space-y-4">
                @csrf
                <x-ui.input name="configuration_revision" label="Configuration revision" value="merchant-v1" required />
                <input type="hidden" name="operation_key" value="merchant.{{ (string) str()->uuid() }}">
                <x-ui.button type="submit" icon="queue-list">Đưa vào hàng đợi</x-ui.button>
            </form>
        </x-ui.card>
        <section>
            <form method="GET" action="{{ route('admin.merchant') }}" class="mb-5 flex items-end gap-3">
                <div><label class="block text-sm font-medium" for="merchant-state">Trạng thái</label><select id="merchant-state" name="state" class="mt-2 min-h-11 rounded-control border border-line bg-surface px-3"><option value="">Tất cả</option>@foreach(['pending','running','partial','completed','failed'] as $option)<option value="{{ $option }}" @selected($state===$option)>{{ $option }}</option>@endforeach</select></div>
                <x-ui.button type="submit" variant="secondary" icon="funnel">Lọc</x-ui.button>
            </form>
            <div class="space-y-3">
                @forelse($batches as $batch)
                    <x-ui.card :title="$batch->configuration_revision.' · '.$batch->state">
                        <p class="text-sm text-ink-muted">{{ $batch->public_id }} · Tổng {{ $batch->total_count }} · Thành công {{ $batch->succeeded_count }} · Lỗi {{ $batch->failed_count }}</p>
                        @if(in_array($batch->state,['partial','failed'],true))<form class="mt-3" method="POST" action="{{ route('admin.merchant.retry',$batch->public_id) }}">@csrf<x-ui.button type="submit" variant="secondary" size="sm" icon="arrow-path">Retry lỗi</x-ui.button></form>@endif
                    </x-ui.card>
                @empty
                    <x-ui.empty-state title="Chưa có Merchant batch" description="Tạo batch đầu tiên khi cần kiểm tra feed." />
                @endforelse
            </div>
            <div class="mt-5">{{ $batches->links() }}</div>
        </section>
    </div>
</div>
@endsection
