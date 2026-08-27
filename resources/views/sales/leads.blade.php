@extends('layouts.staff')

@section('title', 'Lead — Kaiyo Sales')

@section('content')
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="text-sm font-semibold uppercase tracking-widest text-brand">CRM</p><h1 class="mt-2 text-3xl font-bold">Lead</h1><p class="mt-2 text-ink-muted">Nguồn cơ hội bán hàng với quyền và trạng thái do CRM kiểm soát.</p></div><dl class="flex flex-wrap gap-3 text-sm">@foreach($directory->statusCounts as $state => $count)<div class="min-w-24 rounded-control border border-line bg-surface px-4 py-3"><dt class="text-ink-muted">{{ $state }}</dt><dd class="mt-1 text-xl font-bold">{{ number_format($count) }}</dd></div>@endforeach</dl></div>

    @if(session('status'))<x-ui.alert class="mt-6" tone="success" title="Đã cập nhật">{{ session('status') }}</x-ui.alert>@endif
    @if($errors->any())<x-ui.alert class="mt-6" tone="danger" title="Không thể hoàn tất"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

    @if($staffNavigation['can_create_leads'] ?? false)
        <x-ui.card class="mt-8" title="Tạo Lead" description="Lead mới được gán cho chính nhân viên tạo; chuyển đổi dùng workflow riêng.">
            <form method="POST" action="{{ route('sales.leads.store') }}" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">@csrf
                <x-ui.input name="display_name" label="Tên liên hệ" required />
                <x-ui.input name="source" label="Nguồn" required />
                <x-ui.input name="company_name" label="Công ty" />
                <x-ui.input name="email" label="Email" type="email" />
                <x-ui.input name="phone" label="Điện thoại" />
                <x-ui.input name="tax_code" label="Mã số thuế" />
                <div class="md:col-span-2 lg:col-span-3"><x-ui.button type="submit">Tạo Lead</x-ui.button></div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card class="mt-6" title="Tìm và lọc">
        <form method="GET" action="{{ route('sales.leads') }}" class="grid gap-4 md:grid-cols-[1fr_14rem_auto] md:items-end"><x-ui.input name="q" label="Tên, email hoặc điện thoại" :value="$directory->query" autocomplete="off" /><div><label for="lead-status" class="block text-sm font-medium">Trạng thái</label><select id="lead-status" name="status" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3 py-2"><option value="">Tất cả</option>@foreach(['new','qualified','disqualified','converted'] as $state)<option value="{{ $state }}" @selected($directory->status === $state)>{{ $state }}</option>@endforeach</select></div><x-ui.button type="submit">Áp dụng</x-ui.button></form>
    </x-ui.card>

    <div class="mt-6 overflow-hidden rounded-panel border border-line bg-surface shadow-panel"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-line text-left text-sm"><caption class="sr-only">Danh sách Lead</caption><thead class="bg-surface-muted text-ink-muted"><tr><th class="px-5 py-3">Lead</th><th class="px-5 py-3">Liên hệ</th><th class="px-5 py-3">Nguồn</th><th class="px-5 py-3">Trạng thái</th></tr></thead><tbody class="divide-y divide-line">@forelse($directory->leads as $lead)<tr><td class="px-5 py-4"><a href="{{ route('sales.leads.show', $lead['public_id']) }}" class="font-semibold text-brand hover:underline">{{ $lead['display_name'] }}</a><span class="mt-1 block text-ink-muted">{{ $lead['company'] ?? 'Cá nhân' }}</span></td><td class="px-5 py-4"><span class="block">{{ $lead['email'] ?? 'Chưa có email' }}</span><span class="text-ink-muted">{{ $lead['phone'] ?? 'Chưa có số điện thoại' }}</span></td><td class="px-5 py-4">{{ $lead['source'] }}</td><td class="px-5 py-4"><x-ui.badge :tone="$lead['status'] === 'qualified' ? 'success' : 'neutral'">{{ $lead['status'] }}</x-ui.badge></td></tr>@empty<tr><td colspan="4" class="px-5 py-10"><x-ui.empty-state title="Không tìm thấy Lead" description="Thử đổi bộ lọc hoặc tạo Lead mới nếu có quyền." /></td></tr>@endforelse</tbody></table></div></div>

    @if($directory->previousCursor || $directory->nextCursor)<nav class="mt-6 flex justify-between" aria-label="Phân trang Lead">@if($directory->previousCursor)<x-ui.button :href="route('sales.leads', array_filter(['q'=>$directory->query,'status'=>$directory->status,'cursor'=>$directory->previousCursor]))" variant="secondary">Trang trước</x-ui.button>@else<span></span>@endif @if($directory->nextCursor)<x-ui.button :href="route('sales.leads', array_filter(['q'=>$directory->query,'status'=>$directory->status,'cursor'=>$directory->nextCursor]))" variant="secondary">Trang sau</x-ui.button>@endif</nav>@endif
</section>
@endsection
