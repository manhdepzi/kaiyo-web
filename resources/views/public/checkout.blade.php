@extends('layouts.public')
@section('robots', 'noindex,nofollow')

@section('title', 'Thanh toán — Kaiyo')
@section('meta_description', 'Xác nhận địa chỉ, giao hàng và thanh toán cho đơn hàng Kaiyo.')

@section('content')
<section class="mx-auto max-w-5xl px-5 py-12 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Mua hàng</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Thanh toán</h1>
    <p class="mt-3 max-w-3xl text-ink-muted">Giá, thuế, phí giao hàng và tồn kho được tính lại từ dữ liệu chính thức khi bạn đặt hàng.</p>

    @error('checkout')<x-ui.alert class="mt-6" tone="danger" title="Chưa thể đặt hàng">{{ $message }}</x-ui.alert>@enderror
    @if (!$customerLinked)
        <x-ui.alert class="mt-6" tone="warning" title="Cần hoàn tất hồ sơ khách hàng">Tài khoản đã đăng nhập nhưng chưa được liên kết với hồ sơ Customer đang hoạt động.</x-ui.alert>
    @endif
    @if (count($shippingMethods) === 0)
        <x-ui.alert class="mt-6" tone="warning" title="Giao hàng chưa sẵn sàng">Chưa có phương thức giao hàng được phê duyệt và kích hoạt. Hệ thống sẽ không tự đoán phí giao hàng.</x-ui.alert>
    @endif
    @if (count($cart->lines) === 0)
        <x-ui.empty-state class="mt-8" title="Giỏ hàng đang trống" description="Bạn cần ít nhất một sản phẩm trước khi thanh toán.">
            <x-ui.button :href="route('public.search')" icon="magnifying-glass">Tìm sản phẩm</x-ui.button>
        </x-ui.empty-state>
    @else
        <form method="POST" action="{{ route('public.checkout.place') }}" class="mt-8 grid gap-8 lg:grid-cols-[1fr_20rem]">
            @csrf
            <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
            <div class="space-y-6">
                <x-ui.card title="Địa chỉ nhận hàng">
                    @if ($checkoutAddress !== null)<p class="mb-4 text-sm text-ink-muted">Đã điền từ địa chỉ giao hàng mặc định. Bạn vẫn có thể chỉnh sửa cho riêng đơn hàng này; Order sẽ lưu một snapshot bất biến.</p>@endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="recipient_name" label="Người nhận" :value="old('recipient_name', $checkoutAddress['recipient_name'] ?? '')" required />
                        <x-ui.input name="phone" label="Số điện thoại" :value="old('phone', $checkoutAddress['phone'] ?? '')" />
                        <div class="sm:col-span-2"><x-ui.input name="address_line_1" label="Địa chỉ" :value="old('address_line_1', $checkoutAddress['address_line_1'] ?? '')" required /></div>
                        <div class="sm:col-span-2"><x-ui.input name="address_line_2" label="Địa chỉ bổ sung" :value="old('address_line_2', $checkoutAddress['address_line_2'] ?? '')" /></div>
                        <x-ui.input name="locality" label="Quận/Huyện" :value="old('locality', $checkoutAddress['locality'] ?? '')" />
                        <x-ui.input name="subdivision" label="Tỉnh/Thành phố" :value="old('subdivision', $checkoutAddress['subdivision'] ?? '')" />
                        <x-ui.input name="postal_code" label="Mã bưu chính" :value="old('postal_code', $checkoutAddress['postal_code'] ?? '')" />
                        <div><label class="block text-sm font-medium" for="country_code">Quốc gia</label><select id="country_code" name="country_code" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3"><option value="VN" @selected(old('country_code', $checkoutAddress['country_code'] ?? 'VN') === 'VN')>Việt Nam</option></select></div>
                    </div>
                </x-ui.card>
                <x-ui.card title="Thông tin hóa đơn">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="company_name" label="Tên công ty (nếu có)" :value="old('company_name', $checkoutAddress['company_name'] ?? '')" />
                        <x-ui.input name="tax_code" label="Mã số thuế (nếu có)" :value="old('tax_code', $checkoutAddress['tax_code'] ?? '')" />
                    </div>
                    <label class="mt-4 flex items-start gap-3 text-sm"><input type="checkbox" name="invoice_requested" value="1" @checked(old('invoice_requested')) class="mt-1"> <span>Yêu cầu hóa đơn. Thuế chỉ được tính từ cấu hình Finance đang hiệu lực.</span></label>
                </x-ui.card>
            </div>
            <aside class="space-y-5">
                <x-ui.card title="Tóm tắt">
                    <p class="text-sm text-ink-muted">{{ count($cart->lines) }} dòng sản phẩm</p>
                    <p class="mt-2 text-sm">Tổng cuối cùng chỉ hiển thị sau khi hệ thống tính lại thành công.</p>
                </x-ui.card>
                <x-ui.card title="Giao hàng">
                    @forelse ($shippingMethods as $code => $label)
                        <label class="mb-3 flex gap-3 text-sm"><input type="radio" name="shipping_method" value="{{ $code }}" @checked(old('shipping_method') === $code) required> <span>{{ $label }}</span></label>
                    @empty
                        <p class="text-sm text-warning">Chưa có phương thức khả dụng.</p>
                    @endforelse
                    @error('shipping_method')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                </x-ui.card>
                <x-ui.card title="Thanh toán">
                    @foreach ($paymentMethods as $code => $label)
                        <label class="mb-3 flex gap-3 text-sm"><input type="radio" name="payment_method" value="{{ $code }}" @checked(old('payment_method') === $code) required> <span>{{ $label }}</span></label>
                    @endforeach
                    @error('payment_method')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                </x-ui.card>
                <x-ui.button type="submit" class="w-full" :disabled="!$customerLinked || count($shippingMethods) === 0" icon="lock-closed">Đặt hàng an toàn</x-ui.button>
                <x-ui.button :href="route('public.cart')" variant="ghost" class="w-full" icon="arrow-left">Quay lại giỏ hàng</x-ui.button>
            </aside>
        </form>
    @endif
</section>
@endsection
