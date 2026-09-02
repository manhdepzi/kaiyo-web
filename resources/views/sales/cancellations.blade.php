@extends('layouts.staff')

@section('title', 'Yêu cầu hủy đơn — Kaiyo Sales')

@section('content')
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Orders</p>
    <h1 class="mt-2 text-3xl font-bold">Yêu cầu hủy đang chờ</h1>
    <p class="mt-2 max-w-3xl text-ink-muted">Mỗi quyết định được kiểm tra lại trạng thái Order, Payment và Inventory ở phía máy chủ. Người gửi yêu cầu không thể tự quyết định.</p>

    <div class="mt-8 space-y-5">
        @forelse($requests as $item)
            <x-ui.card :title="'Đơn '.$item->order_public_id">
                <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
                    <div>
                        <dl class="grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-ink-muted">Khách hàng</dt><dd class="font-semibold">{{ $item->customer_name }}</dd></div>
                            <div><dt class="text-ink-muted">Trạng thái đơn</dt><dd><x-ui.badge>{{ $item->order_state }}</x-ui.badge></dd></div>
                            <div><dt class="text-ink-muted">Giá trị</dt><dd class="font-semibold">{{ number_format($item->final_amount, 0, ',', '.') }} {{ $item->currency }}</dd></div>
                            <div><dt class="text-ink-muted">Gửi lúc</dt><dd>{{ $item->created_at }}</dd></div>
                        </dl>
                        <p class="mt-4 rounded-control bg-surface-muted p-4 text-sm leading-6"><strong>Lý do khách gửi:</strong> {{ $item->reason }}</p>
                    </div>
                    <form method="POST" action="{{ route('sales.cancellations.decide', $item->public_id) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="expected_version" value="{{ $item->lock_version }}">
                        <input type="hidden" name="decision_key" value="{{ (string) Illuminate\Support\Str::ulid() }}">
                        <div>
                            <label for="decision-{{ $item->public_id }}" class="block text-sm font-medium">Quyết định</label>
                            <select id="decision-{{ $item->public_id }}" name="decision" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3" required>
                                <option value="deny">Từ chối yêu cầu</option>
                                <option value="approve">Phê duyệt hủy đơn</option>
                            </select>
                        </div>
                        <div>
                            <label for="reason-{{ $item->public_id }}" class="block text-sm font-medium">Lý do quyết định</label>
                            <textarea id="reason-{{ $item->public_id }}" name="reason" rows="3" minlength="10" maxlength="1000" class="mt-2 w-full rounded-control border border-line bg-surface p-3" required></textarea>
                        </div>
                        @error('cancellation')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                        <x-ui.button type="submit" icon="check">Ghi nhận quyết định</x-ui.button>
                    </form>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state title="Không có yêu cầu đang chờ" description="Các yêu cầu mới đủ điều kiện sẽ xuất hiện tại đây." />
        @endforelse
    </div>
</section>
@endsection
