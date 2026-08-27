@extends('layouts.public')
@section('title', 'Liên hệ — Kaiyo')
@section('meta_description', 'Liên hệ Kaiyo về nhu cầu sản phẩm, mua hàng hoặc báo giá doanh nghiệp.')
@section('content')
<section class="mx-auto max-w-4xl px-5 py-16 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Liên hệ</p>
    <h1 class="mt-4 text-4xl font-bold tracking-tight">Hãy cho chúng tôi biết nhu cầu của bạn</h1>
    <p class="mt-6 max-w-2xl text-lg leading-8 text-ink-muted">Kênh liên hệ chính thức và biểu mẫu tạo lead sẽ được công bố sau khi cấu hình vận hành được phê duyệt. Chúng tôi không hiển thị số điện thoại, email hay SLA chưa được xác nhận.</p>
    <x-ui.alert class="mt-8" tone="info" title="Kênh liên hệ đang được cấu hình">Trong thời gian này, bạn có thể tra cứu danh mục hoặc đăng nhập để sử dụng các chức năng tài khoản đã được bật.</x-ui.alert>
    <div class="mt-8 flex flex-wrap gap-3">
        <x-ui.button :href="route('public.search')">Xem sản phẩm</x-ui.button>
        <x-ui.button :href="route('login')" variant="secondary">Đăng nhập</x-ui.button>
    </div>
</section>
@endsection
