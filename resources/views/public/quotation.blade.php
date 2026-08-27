@extends('layouts.public')

@section('title', 'Yêu cầu báo giá — Kaiyo')
@section('meta_description', 'Gửi yêu cầu báo giá sản phẩm Kaiyo với dữ liệu giá, thuế và giao hàng được kiểm tra chính thức.')
@section('robots', 'noindex,nofollow')

@section('content')
<section class="mx-auto max-w-4xl px-5 py-12 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">B2B</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Yêu cầu báo giá</h1>
    <p class="mt-3 max-w-3xl text-ink-muted">Bản nháp được tính từ cấu hình giá, VAT và giao hàng đang hiệu lực; hệ thống không tự tạo giá hoặc phí khi thiếu dữ liệu.</p>
    @error('quotation')<x-ui.alert class="mt-6" tone="danger" title="Chưa thể gửi yêu cầu">{{ $message }}</x-ui.alert>@enderror
    @if (!$customerLinked)<x-ui.alert class="mt-6" tone="warning" title="Cần hoàn tất hồ sơ">Tài khoản hiện tại chưa liên kết với Customer đang hoạt động.</x-ui.alert>@endif
    @if (count($shippingMethods) === 0)<x-ui.alert class="mt-6" tone="warning" title="Giao hàng chưa sẵn sàng">Chưa có phương thức giao hàng được kích hoạt.</x-ui.alert>@endif

    <form method="POST" action="{{ route('public.quotation.create') }}" class="mt-8 space-y-6">
        @csrf
        <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
        <x-ui.card title="Sản phẩm cần báo giá">
            <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
                <div>
                    <label class="block text-sm font-medium" for="variant_public_id">Mã biến thể</label>
                    <input id="variant_public_id" name="variant_public_id" value="{{ old('variant_public_id', $variant?->publicId) }}" required maxlength="26" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3" aria-describedby="variant-help">
                    <p id="variant-help" class="mt-2 text-sm text-ink-muted">{{ $variant ? $variant->name.' · SKU '.$variant->sku : 'Mở từ trang sản phẩm để điền tự động mã biến thể.' }}</p>
                    @error('variant_public_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <x-ui.input name="quantity" label="Số lượng" :value="old('quantity', '1')" required />
            </div>
        </x-ui.card>
        <x-ui.card title="Thông tin nhận hàng">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="recipient_name" label="Người liên hệ" :value="old('recipient_name')" required />
                <x-ui.input name="phone" label="Số điện thoại" :value="old('phone')" />
                <div class="sm:col-span-2"><x-ui.input name="address_line_1" label="Địa chỉ" :value="old('address_line_1')" required /></div>
                <x-ui.input name="locality" label="Quận/Huyện" :value="old('locality')" />
                <x-ui.input name="subdivision" label="Tỉnh/Thành phố" :value="old('subdivision')" />
                <input type="hidden" name="country_code" value="VN">
            </div>
        </x-ui.card>
        <x-ui.card title="Điều kiện thương mại">
            @forelse ($shippingMethods as $code => $label)
                <label class="mb-3 flex gap-3 text-sm"><input type="radio" name="shipping_method" value="{{ $code }}" @checked(old('shipping_method') === $code) required> <span>{{ $label }}</span></label>
            @empty
                <p class="text-sm text-warning">Chưa có phương thức giao hàng khả dụng.</p>
            @endforelse
            @error('shipping_method')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            <label class="mt-4 block text-sm font-medium" for="request_note">Ghi chú yêu cầu</label>
            <textarea id="request_note" name="request_note" rows="5" maxlength="2000" class="mt-2 w-full rounded-control border border-line bg-surface px-3 py-2">{{ old('request_note') }}</textarea>
            <label class="mt-4 flex items-start gap-3 text-sm"><input type="checkbox" name="invoice_requested" value="1" @checked(old('invoice_requested')) class="mt-1"> <span>Yêu cầu hóa đơn</span></label>
            <p class="mt-3 text-sm text-ink-muted">V1 B2B sử dụng thanh toán đủ bằng chuyển khoản ngân hàng. Thời hạn mặc định: {{ config('quotation.default_validity_days') }} ngày.</p>
        </x-ui.card>
        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" :disabled="!$customerLinked || count($shippingMethods) === 0">Gửi yêu cầu báo giá</x-ui.button>
            <x-ui.button :href="route('public.search')" variant="ghost">Quay lại sản phẩm</x-ui.button>
        </div>
    </form>
</section>
@endsection
