@extends('layouts.public')
@section('title', 'Giới thiệu — Kaiyo')
@section('meta_description', 'Tìm hiểu cách Kaiyo tổ chức trải nghiệm thương mại B2C và B2B trên một nền tảng thống nhất.')
@section('content')
<article class="mx-auto max-w-4xl px-5 py-16 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Về Kaiyo</p>
    <h1 class="mt-4 text-4xl font-bold tracking-tight">Một nền tảng, hai hành trình mua hàng</h1>
    <p class="mt-6 text-lg leading-8 text-ink-muted">Kaiyo phục vụ cả giao dịch thương mại điện tử và quy trình báo giá doanh nghiệp. Giá, tồn kho, phê duyệt, đơn hàng, thanh toán và giao nhận được nối bằng các trạng thái có thể kiểm tra.</p>
    <div class="mt-12 grid gap-5 sm:grid-cols-2">
        <x-ui.card title="Khách hàng cá nhân" description="Tra cứu, giỏ hàng và checkout với giá cùng tồn kho được xác nhận tại thời điểm cam kết." />
        <x-ui.card title="Khách hàng doanh nghiệp" description="Báo giá có phiên bản, quyền phê duyệt rõ ràng và chuyển đổi thành đúng một đơn hàng." />
    </div>
</article>
@endsection
