@extends('layouts.admin')

@section('title', 'Outbox — Kaiyo')

@section('content')
<div class="mx-auto max-w-7xl px-5 py-10 lg:px-8">
    <h1 class="text-3xl font-bold">Transactional outbox</h1>
    <p class="mt-2 text-ink-muted">Bằng chứng relay nội bộ; payload và hash nhạy cảm không được hiển thị.</p>

    <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach(['pending','publishing','published','dead'] as $summaryState)
            <div class="rounded-control border border-line bg-surface p-4"><dt class="text-sm text-ink-muted">{{ $summaryState }}</dt><dd class="mt-1 text-2xl font-bold">{{ $counts[$summaryState] ?? 0 }}</dd></div>
        @endforeach
    </dl>
    @if($oldest_pending_at)<x-ui.alert class="mt-5" tone="warning" title="Pending backlog" :description="'Bản ghi cũ nhất: '.$oldest_pending_at" />@endif

    <form method="GET" action="{{ route('admin.outbox') }}" class="mt-8 grid items-end gap-4 sm:grid-cols-[12rem_20rem_auto]">
        <div><label class="block text-sm font-medium" for="outbox-state">Trạng thái</label><select id="outbox-state" name="state" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3"><option value="">Tất cả</option>@foreach(['pending','publishing','published','dead'] as $option)<option value="{{ $option }}" @selected($state===$option)>{{ $option }}</option>@endforeach</select></div>
        <x-ui.input name="event_type" label="Event type" :value="$eventType" placeholder="commerce.order.placed" />
        <x-ui.button type="submit" variant="secondary" icon="funnel">Lọc</x-ui.button>
    </form>

    <div class="mt-8 space-y-3">
        @forelse($records as $record)
            <x-ui.card :title="$record->event_type.' v'.$record->event_version.' · '.$record->state">
                <p class="text-sm text-ink-muted">{{ $record->public_id }} · {{ $record->aggregate_type }} / {{ $record->aggregate_public_id }}</p>
                <p class="mt-2 text-sm">Attempts {{ $record->attempt_count }}@if($record->last_error_code) · Error {{ $record->last_error_code }}@endif</p>
            </x-ui.card>
        @empty
            <x-ui.empty-state title="Không có dispatch record" description="Không có bản ghi phù hợp bộ lọc." />
        @endforelse
    </div>
    <div class="mt-5">{{ $records->links() }}</div>
</div>
@endsection
