@extends('layouts.public')
@section('robots', 'noindex,nofollow')

@section('title', 'Yêu cầu báo giá — Kaiyo')
@section('meta_description', 'Theo dõi yêu cầu báo giá Kaiyo.')

@section('content')
<section class="mx-auto max-w-3xl px-5 py-14 lg:px-8">
    @if (session('status'))<x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>@endif
    @error('quotation')<x-ui.alert tone="danger" title="Không thể cập nhật báo giá">{{ $message }}</x-ui.alert>@enderror
    <h1 class="mt-6 text-3xl font-bold">Yêu cầu báo giá {{ $quote['publicId'] }}</h1>
    <div class="mt-8 grid gap-5 sm:grid-cols-3">
        <x-ui.card title="Trạng thái"><p class="font-semibold">{{ $quote['state'] }}</p></x-ui.card>
        <x-ui.card title="Phiên bản"><p class="font-semibold">{{ $quote['revision'] }}</p></x-ui.card>
        <x-ui.card title="Thời hạn yêu cầu"><p class="font-semibold">{{ $quote['validityDays'] }} ngày</p></x-ui.card>
    </div>
    <x-ui.card class="mt-6" title="Sản phẩm">
        <ul class="divide-y divide-line">
            @foreach ($quote['lines'] as $line)<li class="py-3"><span class="font-semibold">{{ $line['name'] }}</span><span class="block text-sm text-ink-muted">SKU {{ $line['sku'] }} · Số lượng {{ $line['quantity'] }}</span></li>@endforeach
        </ul>
        <p class="mt-4 text-sm text-ink-muted">Tổng dự kiến từ bản nháp: {{ number_format($quote['finalAmount'], 0, ',', '.') }} ₫. Báo giá chỉ có hiệu lực sau khi được xử lý, phê duyệt (nếu cần) và phát hành.</p>
    </x-ui.card>
    @if(in_array($quote['state'], ['sent', 'viewed'], true))
        <div class="mt-6 flex flex-wrap gap-3">
            @if($quote['state'] === 'sent')<form method="POST" action="{{ route('public.quotation.access', [$quote['publicId'], 'viewed']) }}">@csrf<input type="hidden" name="event_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}"><x-ui.button type="submit" variant="secondary" icon="eye">Đánh dấu đã xem</x-ui.button></form>@endif
            <form method="POST" action="{{ route('public.quotation.access', [$quote['publicId'], 'accepted']) }}">@csrf<input type="hidden" name="event_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}"><x-ui.button type="submit" icon="check-circle">Chấp nhận báo giá</x-ui.button></form>
            <form method="POST" action="{{ route('public.quotation.access', [$quote['publicId'], 'rejected']) }}">@csrf<input type="hidden" name="event_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}"><x-ui.button type="submit" variant="danger" icon="x-circle">Từ chối</x-ui.button></form>
        </div>
    @endif
</section>
@endsection
