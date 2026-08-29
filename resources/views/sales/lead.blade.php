@extends('layouts.staff')

@section('title', $lead->displayName.' — Kaiyo Sales')

@section('content')
<section class="mx-auto max-w-5xl px-5 py-10 lg:px-8">
    <a href="{{ route('sales.leads') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:underline"><x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />Danh sách Lead</a>
    <div class="mt-5 flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-widest text-brand">Lead</p><h1 class="mt-2 text-3xl font-bold">{{ $lead->displayName }}</h1><p class="mt-2 font-mono text-xs text-ink-muted">{{ $lead->publicId }}</p></div><x-ui.badge :tone="$lead->status === 'qualified' ? 'success' : 'neutral'">{{ $lead->status }}</x-ui.badge></div>

    @if(session('status'))<x-ui.alert class="mt-6" tone="success" title="Đã cập nhật">{{ session('status') }}</x-ui.alert>@endif
    @if($errors->any())<x-ui.alert class="mt-6" tone="danger" title="Không thể hoàn tất"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

    <div class="mt-8 grid gap-6 md:grid-cols-2">
        <x-ui.card title="Thông tin"><dl class="space-y-3 text-sm"><div><dt class="text-ink-muted">Công ty</dt><dd>{{ $lead->companyName ?? 'Cá nhân' }}</dd></div><div><dt class="text-ink-muted">Email</dt><dd>{{ $lead->email ?? 'Chưa có' }}</dd></div><div><dt class="text-ink-muted">Điện thoại</dt><dd>{{ $lead->phone ?? 'Chưa có' }}</dd></div><div><dt class="text-ink-muted">Mã số thuế</dt><dd>{{ $lead->taxCode ?? 'Chưa có' }}</dd></div></dl></x-ui.card>
        @if($lead->inquiryMessage !== null)
            <x-ui.card title="Yêu cầu từ website">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-ink-muted">Chủ đề</dt><dd>{{ $lead->inquiryTopic }}</dd></div>
                    <div><dt class="text-ink-muted">Thời điểm gửi</dt><dd>{{ $lead->inquirySubmittedAt }}</dd></div>
                    <div><dt class="text-ink-muted">Nội dung</dt><dd class="mt-1 whitespace-pre-wrap leading-6">{{ $lead->inquiryMessage }}</dd></div>
                </dl>
            </x-ui.card>
        @endif
        @if($lead->status === 'converted')
            <x-ui.card title="Kết quả chuyển đổi"><p class="text-sm text-ink-muted">Customer và Company được hiển thị bằng public ID; không tự liên kết khi thiếu bằng chứng xác minh.</p><dl class="mt-4 space-y-3 text-sm"><div><dt>Customer</dt><dd class="font-mono">{{ $lead->convertedCustomerPublicId ?? 'Không có' }}</dd></div><div><dt>Company</dt><dd class="font-mono">{{ $lead->convertedCompanyPublicId ?? 'Không có' }}</dd></div></dl></x-ui.card>
        @elseif($lead->canConvert)
            <x-ui.card title="Chuyển đổi Lead" description="Lệnh idempotent tạo Customer/Company mới; không xác nhận hoặc hợp nhất danh tính khi chưa có bằng chứng."><form method="POST" action="{{ route('sales.leads.convert', $lead->publicId) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $conversionKey) }}"><x-ui.button type="submit">Chuyển đổi an toàn</x-ui.button></form></x-ui.card>
        @else
            <x-ui.card title="Chuyển đổi"><x-ui.alert tone="warning" title="Không khả dụng">Trạng thái hoặc entitlement hiện tại không cho phép chuyển đổi.</x-ui.alert></x-ui.card>
        @endif
    </div>

    @if($lead->canUpdate)
        <x-ui.card class="mt-6" title="Cập nhật trạng thái" description="Optimistic lock ngăn ghi đè khi Lead đã thay đổi ở phiên khác."><form method="POST" action="{{ route('sales.leads.update', $lead->publicId) }}" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf @method('PATCH')<input type="hidden" name="expected_version" value="{{ $lead->version }}"><div><label for="lead-state" class="block text-sm font-medium">Trạng thái</label><select id="lead-state" name="status" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3 py-2">@foreach(['new','qualified','disqualified'] as $state)<option value="{{ $state }}" @selected(old('status', $lead->status) === $state)>{{ $state }}</option>@endforeach</select></div><x-ui.input name="source" label="Nguồn" :value="$lead->source" required /><x-ui.button type="submit">Lưu</x-ui.button></form></x-ui.card>
    @endif
</section>
@endsection
