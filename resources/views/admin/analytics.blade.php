@extends('layouts.admin')

@section('title', 'Analytics — Kaiyo')

@section('content')
<div class="mx-auto max-w-7xl px-5 py-10 lg:px-8">
    <h1 class="text-3xl font-bold">Analytics delivery</h1>
    <p class="mt-2 text-ink-muted">Theo dõi bằng chứng giao sự kiện đã được consent; không lưu payload provider hoặc hồ sơ quảng cáo trong Kaiyo.</p>

    <form method="GET" action="{{ route('admin.analytics') }}" class="mt-8 grid items-end gap-4 sm:grid-cols-[12rem_16rem_auto]">
        <div>
            <label class="block text-sm font-medium" for="analytics-state">Trạng thái</label>
            <select id="analytics-state" name="state" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3">
                <option value="">Tất cả</option>
                @foreach(['pending','running','partial','completed','failed'] as $option)
                    <option value="{{ $option }}" @selected($state === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <x-ui.input name="destination" label="Destination" :value="$destination" placeholder="ga4" />
        <x-ui.button type="submit" variant="secondary" icon="funnel">Lọc</x-ui.button>
    </form>

    <div class="mt-8 space-y-3">
        @forelse($batches as $batch)
            <x-ui.card :title="$batch->destination_code.' · '.$batch->state">
                <p class="text-sm text-ink-muted">{{ $batch->public_id }} · Config {{ $batch->configuration_revision }} · Consent {{ $batch->consent_revision }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-ink-muted">Tổng</dt><dd class="font-semibold">{{ $batch->total_count }}</dd></div>
                    <div><dt class="text-ink-muted">Thành công</dt><dd class="font-semibold">{{ $batch->succeeded_count }}</dd></div>
                    <div><dt class="text-ink-muted">Suppressed</dt><dd class="font-semibold">{{ $batch->suppressed_count }}</dd></div>
                    <div><dt class="text-ink-muted">Lỗi</dt><dd class="font-semibold">{{ $batch->failed_count }}</dd></div>
                </dl>
            </x-ui.card>
        @empty
            <x-ui.empty-state title="Chưa có Analytics batch" description="Sự kiện chỉ xuất hiện sau khi producer và consent hợp lệ được giao qua provider-neutral boundary." />
        @endforelse
    </div>
    <div class="mt-5">{{ $batches->links() }}</div>
</div>
@endsection
