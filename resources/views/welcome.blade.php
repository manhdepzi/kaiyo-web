@extends('layouts.public')

@section('title', 'Kaiyo — Sản phẩm và giải pháp cho doanh nghiệp')
@section('meta_description', 'Tra cứu sản phẩm theo tên hoặc SKU, tiếp cận quy trình mua hàng và báo giá minh bạch tại Kaiyo.')

@section('content')
<section class="bg-canvas" aria-label="Sản phẩm nổi bật">
    <h1 class="sr-only">Tìm đúng sản phẩm. Đi đến quyết định nhanh hơn.</h1>
    <div class="mx-auto max-w-[1600px] px-0 sm:px-5 lg:px-8 lg:py-6">
        <div data-slideshow data-interval="3000" class="relative overflow-hidden bg-surface shadow-panel sm:rounded-panel">
            <div class="relative aspect-[3951/1672] w-full">
                @foreach($heroSlides as $slide)
                    <article data-slide @if(!$loop->first) hidden @endif aria-hidden="{{ $loop->first ? 'false' : 'true' }}" class="absolute inset-0">
                        @if($slide->imagePath)
                            @if($slide->ctaUrl)<a href="{{ $slide->ctaUrl }}" class="block h-full" aria-label="{{ $slide->headline }}">@endif
                            <img src="{{ $slide->imagePath }}" alt="{{ $slide->headline }}" width="3951" height="1672" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif class="h-full w-full object-cover">
                            @if($slide->ctaUrl)</a>@endif
                        @else
                            <div class="flex h-full items-center bg-brand-soft px-8 py-12 sm:px-16">
                                <div class="max-w-2xl">
                                    <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-5xl">{{ $slide->headline }}</h1>
                                    @if($slide->body)<p class="mt-5 text-lg text-ink-muted">{{ $slide->body }}</p>@endif
                                    @if($slide->ctaLabel && $slide->ctaUrl)<div class="mt-7"><x-ui.button :href="$slide->ctaUrl" icon="arrow-right" icon-position="end">{{ $slide->ctaLabel }}</x-ui.button></div>@endif
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if(count($heroSlides) > 1)
                <button type="button" data-slide-prev class="absolute left-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-sm transition hover:scale-105 hover:bg-black/65 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Banner trước"><x-heroicon-o-chevron-left class="size-6" aria-hidden="true" /></button>
                <button type="button" data-slide-next class="absolute right-3 top-1/2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-sm transition hover:scale-105 hover:bg-black/65 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Banner tiếp theo"><x-heroicon-o-chevron-right class="size-6" aria-hidden="true" /></button>
                <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-2 rounded-full bg-black/35 px-3 py-2" role="group" aria-label="Chọn banner">
                    @foreach($heroSlides as $slide)
                        <button type="button" data-slide-dot="{{ $loop->index }}" class="size-2.5 rounded-full border border-white bg-white {{ $loop->first ? '' : 'opacity-45' }}" aria-label="Xem banner {{ $loop->iteration }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>

@if (count($featuredProducts) > 0)
<section class="mx-auto max-w-7xl px-5 py-16 lg:px-8" aria-labelledby="featured-products-title">
    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-brand">Danh mục thực tế</p>
            <h2 id="featured-products-title" class="mt-3 text-3xl font-bold tracking-tight">Sản phẩm nổi bật</h2>
            <p class="mt-3 max-w-2xl text-ink-muted">Ảnh và biến thể đang được hiển thị từ dữ liệu Catalog để bạn kiểm tra trực tiếp.</p>
        </div>
        <x-ui.button :href="route('public.search')" variant="secondary" icon="arrow-right" icon-position="end">Xem tất cả</x-ui.button>
    </div>
    <div class="mt-9 grid gap-6 md:grid-cols-3">
        @foreach ($featuredProducts as $product)
            <article class="group overflow-hidden rounded-panel border border-line bg-surface shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-panel">
                <a href="{{ route('public.product', $product->slug) }}" class="block aspect-[4/3] overflow-hidden bg-white">
                    @if (isset($product->images[0]))
                        <img src="{{ $product->images[0]->url }}" alt="{{ $product->images[0]->alt }}" width="{{ $product->images[0]->width }}" height="{{ $product->images[0]->height }}" loading="lazy" class="h-full w-full object-contain p-6 transition duration-500 ease-out group-hover:scale-110">
                    @else
                        <span class="grid h-full place-items-center text-sm text-ink-muted">Ảnh đang cập nhật</span>
                    @endif
                </a>
                <div class="p-6">
                    <p class="text-xs font-semibold uppercase tracking-widest text-brand">{{ $product->category->name }}</p>
                    <h3 class="mt-2 text-xl font-semibold"><a class="hover:text-brand" href="{{ route('public.product', $product->slug) }}">{{ $product->name }}</a></h3>
                    <p class="mt-3 text-sm text-ink-muted">{{ count($product->variants) }} biến thể đang công bố</p>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

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
        <x-ui.button :href="route('public.search')" size="lg" icon="magnifying-glass">Xem sản phẩm</x-ui.button>
    </div>
</section>
@endsection
