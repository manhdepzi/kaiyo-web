@extends('layouts.staff')

@section('title', $company->displayName.' — Kaiyo Sales')

@section('content')
<section class="mx-auto max-w-5xl px-5 py-10 lg:px-8">
    <a href="{{ route('sales.companies') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:underline"><x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />Danh sách công ty</a>
    <div class="mt-5 flex flex-wrap justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-widest text-brand">Company 360</p><h1 class="mt-2 text-3xl font-bold">{{ $company->displayName }}</h1><p class="mt-2 text-ink-muted">{{ $company->legalName }}</p><p class="mt-1 font-mono text-xs text-ink-muted">{{ $company->publicId }}</p></div><x-ui.badge :tone="$company->status==='active'?'success':'neutral'">{{ $company->status }}</x-ui.badge></div>
    @if(session('status'))<x-ui.alert class="mt-6" tone="success" title="Đã cập nhật">{{ session('status') }}</x-ui.alert>@endif
    @if($errors->any())<x-ui.alert class="mt-6" tone="danger" title="Không thể hoàn tất"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif
    <x-ui.card class="mt-8" title="Hồ sơ"><dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-ink-muted">Mã số thuế</dt><dd class="mt-1 font-medium">{{ $company->taxCode ?? 'Chưa có' }}</dd></div><div><dt class="text-ink-muted">Phiên bản</dt><dd class="mt-1 font-medium">{{ $company->version }}</dd></div></dl></x-ui.card>
    @if($company->canManageMembers)<x-ui.card class="mt-6" title="Thêm thành viên" description="Thêm membership không tự cấp bất kỳ capability nào."><form method="POST" action="{{ route('sales.companies.members.store', $company->publicId) }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">@csrf<x-ui.input class="flex-1" name="member_public_id" label="Public ID tài khoản" required minlength="26" maxlength="26" /><x-ui.button type="submit">Thêm thành viên</x-ui.button></form></x-ui.card>@endif
    <x-ui.card class="mt-6" title="Thành viên"><div class="space-y-3">@forelse($company->members as $member)<article class="rounded-control border border-line bg-surface-muted p-4 text-sm"><div class="flex flex-wrap justify-between gap-3"><span><strong>{{ $member['email'] }}</strong><small class="mt-1 block font-mono text-ink-muted">{{ $member['account_public_id'] }}</small></span><x-ui.badge :tone="$member['status']==='active'?'success':'neutral'">{{ $member['status'] }}</x-ui.badge></div><p class="mt-2 text-ink-muted">Bắt đầu {{ $member['starts_at'] }}@if($member['ends_at']) · kết thúc {{ $member['ends_at'] }}@endif</p></article>@empty<x-ui.empty-state title="Chưa có thành viên" description="Membership được quản lý riêng với capability." />@endforelse</div></x-ui.card>
</section>
@endsection
