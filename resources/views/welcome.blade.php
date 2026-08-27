@extends('layouts.public')

@section('title', 'Kaiyo — Sản phẩm và giải pháp cho doanh nghiệp')
@section('meta_description', 'Tra cứu sản phẩm theo tên hoặc SKU, tiếp cận quy trình mua hàng và báo giá minh bạch tại Kaiyo.')

@section('content')
<section class="theme-dark overflow-hidden bg-canvas text-ink">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-20 lg:grid-cols-[1.15fr_0.85fr] lg:px-8 lg:py-28">
        <div>
            <x-ui.badge tone="info">Commerce · B2B · CRM</x-ui.badge>
            <h1 class="mt-6 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">{{ $heroBanner?->headline ?? 'Tìm đúng sản phẩm. Đi đến quyết định nhanh hơn.' }}</h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-ink-muted">{{ $heroBanner?->body ?? 'Tra cứu theo tên hoặc SKU và bắt đầu quy trình mua hàng hay báo giá từ một nguồn thông tin thống nhất.' }}</p>
            <form action="{{ route('public.search') }}" method="GET" role="search" class="mt-8 flex max-w-2xl flex-col gap-3 sm:flex-row">
                <label for="hero-search" class="sr-only">Tìm sản phẩm hoặc SKU</label>
                <input id="hero-search" name="q" type="search" maxlength="100" placeholder="Tên sản phẩm hoặc SKU" class="min-h-12 flex-1 rounded-control border border-line bg-surface px-4 text-ink placeholder:text-ink-muted">
                <x-ui.button type="submit" size="lg">Tìm sản phẩm</x-ui.button>
            </form>
            @if($heroBanner?->ctaLabel && $heroBanner?->ctaUrl)<div class="mt-4"><x-ui.button :href="$heroBanner->ctaUrl" variant="secondary">{{ $heroBanner->ctaLabel }}</x-ui.button></div>@endif
        </div>
        <div class="relative mx-auto aspect-square w-full max-w-md" aria-hidden="true">
            <div class="absolute inset-4 rounded-full border border-line"></div>
            <div class="absolute inset-16 rounded-full border border-brand"></div>
            <div class="absolute left-1/2 top-6 h-[calc(100%-3rem)] w-px -translate-x-1/2 bg-line"></div>
            <div class="absolute left-6 top-1/2 h-px w-[calc(100%-3rem)] -translate-y-1/2 bg-line"></div>
            <div class="absolute inset-1/3 rounded-full bg-brand-soft"></div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-5 py-16 lg:px-8" aria-labelledby="journey-title">
    <div class="max-w-2xl">
        <p class="text-sm font-semibold uppercase tracking-widest text-brand">Một luồng xuyên suốt</p>
        <h2 id="journey-title" class="mt-3 text-3xl font-bold tracking-tight">Từ tra cứu đến giao nhận</h2>
        <p class="mt-4 text-ink-muted">Mỗi bước dùng dữ liệu đã được hệ thống xác nhận; giá và tồn kho cuối cùng luôn được kiểm tra lại trước khi cam kết.</p>
    </div>
    <div class="mt-10 grid gap-5 md:grid-cols-3">
        <x-ui.card title="01 · Khám phá" description="Tìm kiếm theo tên hoặc SKU với bộ lọc rõ ràng và nội dung được render từ máy chủ." />
        <x-ui.card title="02 · Mua hoặc báo giá" description="Chọn luồng phù hợp cho nhu cầu cá nhân hay doanh nghiệp; mọi thay đổi quan trọng được xác nhận." />
        <x-ui.card title="03 · Theo dõi" description="Đơn hàng, thanh toán và giao nhận dùng trạng thái có bằng chứng, không suy đoán từ giao diện." />
    </div>
</section>

<section class="border-y border-line bg-surface">
    <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 px-5 py-12 sm:flex-row sm:items-center lg:px-8">
        <div>
            <h2 class="text-2xl font-bold">Bắt đầu với danh mục Kaiyo</h2>
            <p class="mt-2 text-ink-muted">Duyệt toàn bộ sản phẩm đang được công bố trên hệ thống.</p>
        </div>
        <x-ui.button :href="route('public.search')" size="lg">Xem sản phẩm</x-ui.button>
    </div>
</section>
@endsection
