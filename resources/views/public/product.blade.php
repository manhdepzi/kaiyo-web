@extends('layouts.public')

@php
    $canonicalUrl = route('public.product', $product->slug);
    $metaDescription = $product->seoDescription ?: ($product->description
        ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $product->description)), 155, '')
        : 'Xem thông tin, hình ảnh, thông số và các cấu hình của '.$product->name.' tại Kaiyo.');
    $seoTitle = $product->seoTitle ?: \Illuminate\Support\Str::limit($product->name.' | '.$product->category->name, 58, '').' | Kaiyo';
    $primaryImage = $product->images[0] ?? null;
    $mediaCount = count($product->images) + ($productVideo === null ? 0 : 1);
    $schemaContextKey = chr(64).'context';
    $schemaTypeKey = chr(64).'type';
    $breadcrumbSchema = [
        $schemaContextKey => 'https://schema.org',
        $schemaTypeKey => 'BreadcrumbList',
        'itemListElement' => [
            [$schemaTypeKey => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
            [$schemaTypeKey => 'ListItem', 'position' => 2, 'name' => $product->category->name, 'item' => route('public.category', $product->category->slug)],
            [$schemaTypeKey => 'ListItem', 'position' => 3, 'name' => $product->name, 'item' => $canonicalUrl],
        ],
    ];
@endphp

@section('title', $seoTitle)
@section('meta_description', $metaDescription)
@section('canonical', $canonicalUrl)
@section('robots', 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1')

@push('head')
<meta property="og:type" content="product">
<meta property="og:locale" content="vi_VN">
<meta property="og:site_name" content="Kaiyo">
<meta property="og:title" content="{{ $product->name }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
@if($primaryImage)<meta property="og:image" content="{{ $primaryImage->url }}"><meta property="og:image:alt" content="{{ $primaryImage->alt }}">@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $product->name }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
@if($primaryImage)<meta name="twitter:image" content="{{ $primaryImage->url }}">@endif
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
<article class="mx-auto max-w-[90rem] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
    <nav class="flex flex-wrap items-center gap-2 text-sm text-ink-muted" aria-label="Đường dẫn">
        <a class="transition hover:text-brand" href="{{ route('home') }}">Trang chủ</a>
        <x-heroicon-o-chevron-right class="size-4" aria-hidden="true" />
        <a class="transition hover:text-brand" href="{{ route('public.category', $product->category->slug) }}">{{ $product->category->name }}</a>
        <x-heroicon-o-chevron-right class="size-4" aria-hidden="true" />
        <span class="line-clamp-1" aria-current="page">{{ $product->name }}</span>
    </nav>

    <div class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Khám phá danh mục">
        <a href="{{ route('public.category', 'ong-gio-phu-kien') }}" class="shrink-0 rounded-full border border-line bg-surface px-4 py-2 text-sm font-semibold transition hover:border-brand hover:text-brand">Ống gió & Phụ kiện</a>
        <a href="{{ route('public.category', 'mieng-gio-van-gio') }}" class="shrink-0 rounded-full border border-line bg-surface px-4 py-2 text-sm font-semibold transition hover:border-brand hover:text-brand">Miệng gió & Van gió</a>
        <a href="{{ route('public.projects') }}" class="shrink-0 rounded-full border border-line bg-surface px-4 py-2 text-sm font-semibold transition hover:border-brand hover:text-brand">Dự án Kaiyo</a>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2 xl:grid-cols-12 xl:items-start">
        <section data-product-gallery class="min-w-0 xl:col-span-5" aria-label="Thư viện ảnh {{ $product->name }}">
            @if (count($product->images) > 0)
                <div class="rounded-panel border border-line bg-surface p-3 shadow-sm sm:p-4">
                    <div class="grid gap-3 sm:grid-cols-[4.75rem_minmax(0,1fr)]">
                        <div class="order-2 flex gap-2 overflow-x-auto sm:order-1 sm:flex-col" aria-label="Chọn ảnh sản phẩm">
                            @foreach ($product->images as $image)
                                <button type="button" data-gallery-thumb="{{ $loop->index }}" aria-label="Xem ảnh {{ $loop->iteration }} của {{ $product->name }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" class="size-[4.5rem] shrink-0 overflow-hidden rounded-xl border-2 border-line bg-white p-1 transition hover:border-brand aria-[current=true]:border-brand aria-[current=true]:shadow-md">
                                    <img src="{{ $image->url }}" alt="" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy" decoding="async" class="h-full w-full object-contain">
                                </button>
                            @endforeach
                            @if($productVideo !== null)
                                <button type="button" data-gallery-thumb="{{ count($product->images) }}" aria-label="Xem video minh họa của {{ $product->name }}" aria-current="false" class="group relative size-[4.5rem] shrink-0 overflow-hidden rounded-xl border-2 border-line bg-ink p-1 transition hover:border-brand aria-[current=true]:border-brand aria-[current=true]:shadow-md">
                                    @if($primaryImage)<img src="{{ $primaryImage->url }}" alt="" width="{{ $primaryImage->width }}" height="{{ $primaryImage->height }}" loading="lazy" decoding="async" class="h-full w-full object-cover opacity-60">@endif
                                    <span class="absolute inset-0 grid place-items-center text-white"><span class="grid size-8 place-items-center rounded-full bg-brand shadow"><x-heroicon-s-play class="ml-0.5 size-4" aria-hidden="true" /></span></span>
                                    <span class="sr-only">Video</span>
                                </button>
                            @endif
                        </div>
                        <div class="order-1 sm:order-2">
                            <div data-deep-zoom class="product-deep-zoom relative aspect-square overflow-hidden rounded-[0.875rem] bg-white" aria-label="Rê chuột trên ảnh để phóng to vừa phải">
                                @foreach ($product->images as $image)
                                    <figure data-gallery-slide @if(!$loop->first) hidden @endif aria-hidden="{{ $loop->first ? 'false' : 'true' }}" class="product-gallery-slide-enter absolute inset-0">
                                        <img data-zoom-image src="{{ $image->url }}" alt="{{ $image->alt }} – ảnh sản phẩm {{ $loop->iteration }}" width="{{ $image->width }}" height="{{ $image->height }}" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async" class="h-full w-full object-contain p-4 sm:p-7">
                                    </figure>
                                @endforeach
                                @if($productVideo !== null)
                                    <figure data-gallery-slide hidden aria-hidden="true" class="product-gallery-slide-enter absolute inset-0 grid place-items-center bg-ink">
                                        <video controls playsinline preload="metadata" @if($primaryImage) poster="{{ $primaryImage->url }}" @endif class="h-full w-full object-contain" aria-label="{{ $productVideo['title'] }}">
                                            <source src="{{ $productVideo['url'] }}" type="{{ $productVideo['mime'] }}">
                                            Trình duyệt của bạn chưa hỗ trợ phát video HTML5.
                                        </video>
                                        <span class="pointer-events-none absolute left-3 top-3 rounded-full bg-warning px-3 py-1.5 text-xs font-bold text-on-warning">VIDEO MẪU</span>
                                    </figure>
                                @endif
                                <div class="pointer-events-none absolute bottom-3 left-3 rounded-full bg-ink/75 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur"><span data-gallery-counter>1</span>/{{ $mediaCount }}</div>
                                @if($mediaCount > 1)
                                    <button type="button" data-gallery-prev class="absolute left-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-ink shadow-lg transition hover:bg-brand hover:text-on-brand" aria-label="Ảnh trước"><x-heroicon-o-chevron-left class="size-5" /></button>
                                    <button type="button" data-gallery-next class="absolute right-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-ink shadow-lg transition hover:bg-brand hover:text-on-brand" aria-label="Ảnh tiếp theo"><x-heroicon-o-chevron-right class="size-5" /></button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 hidden items-center justify-center gap-2 text-xs text-ink-muted [@media(hover:hover)]:flex"><x-heroicon-o-magnifying-glass-plus class="size-4 text-brand" aria-hidden="true" />Di chuyển chuột trên ảnh để xem chi tiết</p>
                </div>
            @else
                <div class="grid aspect-square place-items-center rounded-panel border border-line bg-surface-muted p-8 text-center text-ink-muted" role="img" aria-label="Hình ảnh sản phẩm chưa được công bố">Hình ảnh đang được cập nhật</div>
            @endif
        </section>

        <section class="rounded-panel border border-line bg-surface p-5 shadow-sm sm:p-6 lg:col-span-1 xl:col-span-4" aria-labelledby="product-title">
            <div class="flex flex-wrap items-center gap-2">
                @if ($product->brand)<a class="text-xs font-bold uppercase tracking-[0.16em] text-brand hover:underline" href="{{ route('public.brand', $product->brand->slug) }}">{{ $product->brand->name }}</a>@endif
                <x-ui.badge tone="success">Đang nhận yêu cầu</x-ui.badge>
            </div>
            <h1 id="product-title" class="mt-3 text-2xl font-bold leading-tight tracking-tight sm:text-3xl">{{ $product->name }}</h1>
            <p class="mt-3 text-sm font-medium text-ink-muted">Danh mục: <a class="text-brand hover:underline" href="{{ route('public.category', $product->category->slug) }}">{{ $product->category->name }}</a></p>
            @error('wishlist')<x-ui.alert class="mt-4" tone="warning">{{ $message }}</x-ui.alert>@enderror
            <div class="mt-4">
                @auth
                    @if($isWishlisted)
                        <form method="POST" action="{{ route('account.wishlist.destroy', $product->publicId) }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="secondary" size="sm" icon="heart">Đã lưu · Bỏ yêu thích</x-ui.button></form>
                    @else
                        <form method="POST" action="{{ route('account.wishlist.store', $product->publicId) }}">@csrf<x-ui.button type="submit" variant="secondary" size="sm" icon="heart">Lưu sản phẩm yêu thích</x-ui.button></form>
                    @endif
                @else
                    <x-ui.button :href="route('login')" variant="secondary" size="sm" icon="heart">Đăng nhập để lưu sản phẩm</x-ui.button>
                @endauth
            </div>
            @if ($product->description)<p class="mt-4 whitespace-pre-line text-[0.9375rem] leading-7 text-ink-muted">{{ $product->description }}</p>@endif

            <div class="mt-5 grid grid-cols-2 gap-2 border-y border-line py-4 text-sm">
                <div class="flex items-start gap-2 rounded-xl bg-surface-muted p-3"><x-heroicon-o-adjustments-horizontal class="mt-0.5 size-5 shrink-0 text-brand" aria-hidden="true" /><span>Sản xuất theo cấu hình</span></div>
                <div class="flex items-start gap-2 rounded-xl bg-surface-muted p-3"><x-heroicon-o-document-check class="mt-0.5 size-5 shrink-0 text-brand" aria-hidden="true" /><span>Xác nhận trước báo giá</span></div>
            </div>

            <section class="mt-5" aria-labelledby="variant-title">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="variant-title" class="text-lg font-bold">Chọn biến thể</h2>
                    <span class="text-xs text-ink-muted">{{ count($product->variants) }} cấu hình</span>
                </div>
                @if (count($product->variants) === 0)
                    <p class="mt-3 text-sm text-ink-muted">Chưa có biến thể khả dụng.</p>
                @else
                    <div class="mt-3 grid gap-2" role="radiogroup" aria-label="Biến thể của {{ $product->name }}">
                        @foreach ($product->variants as $variant)
                            <label data-variant-option class="group relative cursor-pointer">
                                <input class="peer sr-only" type="radio" name="product_variant_preview" value="{{ $variant->publicId }}" data-sku="{{ $variant->sku }}" data-name="{{ $variant->name }}" @checked($loop->first)>
                                <span class="flex min-h-16 items-center gap-3 rounded-xl border-2 border-line bg-surface px-4 py-3 transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand group-hover:shadow-sm peer-checked:border-brand peer-checked:bg-brand-soft peer-focus-visible:ring-4 peer-focus-visible:ring-focus/30">
                                    <span data-variant-check class="grid size-8 shrink-0 place-items-center rounded-full border border-line bg-white text-brand"><x-heroicon-o-check class="size-4 opacity-0 transition" aria-hidden="true" /></span>
                                    <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $variant->name }}</span><span class="mt-0.5 block truncate text-xs text-ink-muted">SKU: {{ $variant->sku }}</span></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </section>

            @if(count($specifications) > 0)
                <section class="mt-6" aria-labelledby="quick-spec-title">
                    <h2 id="quick-spec-title" class="text-base font-bold">Thuộc tính nổi bật</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-2">
                        @foreach(array_slice($specifications, 0, 4, true) as $label => $value)
                            <div class="rounded-xl bg-surface-muted p-3"><dt class="text-xs text-ink-muted">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </section>

        <aside class="lg:col-span-2 xl:sticky xl:top-5 xl:col-span-3 xl:self-start" aria-label="Đặt hàng và yêu cầu báo giá">
            <div class="overflow-hidden rounded-panel border border-line bg-surface shadow-panel">
                <div class="bg-gradient-to-br from-brand-soft to-surface p-5">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand">Cấu hình đang chọn</p>
                    <p class="mt-2 font-bold" data-selected-variant>{{ $product->variants[0]->name ?? 'Chưa có cấu hình' }}</p>
                    <p class="mt-1 text-xs text-ink-muted" data-selected-sku>@isset($product->variants[0])SKU: {{ $product->variants[0]->sku }}@endisset</p>
                </div>

                <div class="p-5">
                    <x-ui.alert tone="info" title="Giá và tồn kho theo thời điểm">Hệ thống xác nhận đúng biến thể và số lượng trước khi tạo cam kết.</x-ui.alert>

                    @if(count($product->variants) > 0)
                        <form method="POST" action="{{ route('public.cart.lines.store') }}" class="mt-5" data-product-order-form>
                            @csrf
                            <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                            <input type="hidden" name="variant_public_id" value="{{ $product->variants[0]->publicId }}" data-selected-variant-input>
                            <label for="quantity" class="block text-sm font-semibold">Số lượng</label>
                            <div class="mt-2 grid grid-cols-[3rem_minmax(0,1fr)_3rem] overflow-hidden rounded-xl border border-line bg-surface">
                                <button type="button" data-quantity-decrease class="grid min-h-12 place-items-center border-r border-line transition hover:bg-surface-muted" aria-label="Giảm số lượng"><x-heroicon-o-minus class="size-4" /></button>
                                <input id="quantity" name="quantity" type="number" value="1" min="1" step="1" inputmode="numeric" required class="min-w-0 border-0 bg-transparent text-center font-bold focus:outline-none">
                                <button type="button" data-quantity-increase class="grid min-h-12 place-items-center border-l border-line transition hover:bg-surface-muted" aria-label="Tăng số lượng"><x-heroicon-o-plus class="size-4" /></button>
                            </div>
                            <x-ui.button type="submit" class="mt-4 w-full" icon="shopping-cart">Thêm vào giỏ hàng</x-ui.button>
                            <x-ui.button :href="route('public.quotation', ['variant' => $product->variants[0]->publicId])" data-variant-quote data-base-url="{{ route('public.quotation') }}" class="mt-3 w-full" variant="secondary" icon="document-text">Yêu cầu báo giá</x-ui.button>
                        </form>
                    @endif

                    <a href="{{ route('public.contact', ['topic' => 'product']) }}" class="mt-3 flex min-h-12 items-center justify-center gap-2 rounded-control border border-line px-4 text-sm font-semibold transition hover:border-brand hover:text-brand"><x-heroicon-o-chat-bubble-left-right class="size-5" aria-hidden="true" />Trao đổi với tư vấn viên</a>

                    <ul class="mt-5 space-y-3 border-t border-line pt-5 text-sm text-ink-muted">
                        <li class="flex gap-2"><x-heroicon-o-shield-check class="size-5 shrink-0 text-success" aria-hidden="true" /><span>Dữ liệu thương mại được kiểm tra phía máy chủ.</span></li>
                        <li class="flex gap-2"><x-heroicon-o-arrow-path class="size-5 shrink-0 text-brand" aria-hidden="true" /><span>Cấu hình được xác nhận lại trước khi đặt hàng.</span></li>
                        <li class="flex gap-2"><x-heroicon-o-wrench-screwdriver class="size-5 shrink-0 text-brand" aria-hidden="true" /><span>Hỗ trợ yêu cầu sản xuất theo hồ sơ dự án.</span></li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>

    <nav class="mt-12 flex gap-2 overflow-x-auto border-b border-line" aria-label="Nội dung sản phẩm">
        <a href="#mo-ta" class="shrink-0 border-b-2 border-brand px-4 py-3 text-sm font-bold text-brand">Mô tả</a>
        <a href="#thong-so" class="shrink-0 border-b-2 border-transparent px-4 py-3 text-sm font-semibold hover:border-line hover:text-brand">Thông số kỹ thuật</a>
        <a href="#danh-gia" class="shrink-0 border-b-2 border-transparent px-4 py-3 text-sm font-semibold hover:border-line hover:text-brand">Đánh giá ({{ count($productReviews) }})</a>
        <a href="#bao-gia" class="shrink-0 border-b-2 border-transparent px-4 py-3 text-sm font-semibold hover:border-line hover:text-brand">Tư vấn & báo giá</a>
    </nav>

    <div class="grid gap-8 pt-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-10">
            <section id="mo-ta" class="scroll-mt-24" aria-labelledby="description-title">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-brand">Tổng quan sản phẩm</p>
                <h2 id="description-title" class="mt-2 text-2xl font-bold">{{ $product->name }} cho hệ thống thông gió</h2>
                <div class="mt-5 rounded-panel border border-line bg-surface p-5 leading-8 text-ink-muted sm:p-7">
                    @if($product->detailedDescription || $product->description)
                        <p class="whitespace-pre-line">{{ $product->detailedDescription ?: $product->description }}</p>
                    @else
                        <p>{{ $product->name }} thuộc danh mục {{ $product->category->name }}. Sản phẩm được tiếp nhận theo cấu hình công bố và xác nhận lại theo yêu cầu thực tế trước khi báo giá.</p>
                    @endif
                    <p class="mt-4">Kaiyo tiếp nhận kích thước, số lượng và hồ sơ kỹ thuật của công trình để đối chiếu đúng biến thể. Giá, tồn kho, thuế và phương án giao hàng chỉ trở thành cam kết sau bước xác nhận chính thức.</p>
                </div>
            </section>

            <section id="thong-so" class="scroll-mt-24" aria-labelledby="specification-title">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-brand">Dữ liệu đã công bố</p>
                <h2 id="specification-title" class="mt-2 text-2xl font-bold">Thông số kỹ thuật {{ $product->name }}</h2>
                <dl class="mt-5 grid overflow-hidden rounded-panel border border-line bg-surface sm:grid-cols-2">
                    @foreach($specifications as $label => $value)
                        <div class="grid grid-cols-[7rem_1fr] gap-3 border-b border-line p-4 odd:bg-surface-muted sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-ink-muted">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                    @endforeach
                    <div class="grid grid-cols-[7rem_1fr] gap-3 border-b border-line p-4 odd:bg-surface-muted sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-ink-muted">Danh mục</dt><dd class="font-semibold">{{ $product->category->name }}</dd></div>
                    <div class="grid grid-cols-[7rem_1fr] gap-3 border-b border-line p-4 odd:bg-surface-muted sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-ink-muted">Thương hiệu</dt><dd class="font-semibold">{{ $product->brand?->name ?? 'Kaiyo' }}</dd></div>
                    <div class="grid grid-cols-[7rem_1fr] gap-3 border-b border-line p-4 odd:bg-surface-muted sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-ink-muted">Biến thể</dt><dd class="font-semibold">{{ count($product->variants) }} cấu hình đang công bố</dd></div>
                </dl>
            </section>
        </div>

        <aside id="bao-gia" class="scroll-mt-24 rounded-panel border border-line bg-gradient-to-br from-brand-soft to-surface p-6 lg:self-start" aria-labelledby="project-support-title">
            <x-heroicon-o-document-text class="size-9 text-brand" aria-hidden="true" />
            <h2 id="project-support-title" class="mt-4 text-xl font-bold">Cần cấu hình riêng?</h2>
            <p class="mt-3 text-sm leading-6 text-ink-muted">Gửi bản vẽ, kích thước, số lượng hoặc yêu cầu nghiệm thu để đội ngũ Kaiyo đối chiếu phương án phù hợp.</p>
            <x-ui.button :href="route('public.contact', ['topic' => 'product'])" class="mt-5 w-full" icon="paper-airplane">Gửi yêu cầu dự án</x-ui.button>
        </aside>
    </div>

    <section id="danh-gia" class="mt-12 scroll-mt-24 border-t border-line pt-10" aria-labelledby="reviews-title">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-sm font-bold uppercase tracking-[0.16em] text-brand">Mua hàng đã xác minh</p><h2 id="reviews-title" class="mt-2 text-2xl font-bold">Đánh giá sản phẩm</h2></div><x-ui.badge tone="info">{{ count($productReviews) }} đánh giá đã duyệt</x-ui.badge></div>
        @error('review')<x-ui.alert class="mt-5" tone="warning">{{ $message }}</x-ui.alert>@enderror
        @auth
            @if($ownReview === null || $ownReview['status'] !== 'approved')
                <x-ui.card class="mt-6" title="{{ $ownReview === null ? 'Viết đánh giá' : 'Cập nhật đánh giá đang '.$ownReview['status'] }}" description="Chỉ đơn đã giao/hoàn tất chứa sản phẩm này mới đủ điều kiện. Nội dung cần được duyệt trước khi công khai.">
                    <form method="POST" action="{{ route('account.reviews.store', $product->publicId) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        @if($ownReview)<input type="hidden" name="expected_version" value="{{ $ownReview['version'] }}">@endif
                        <div><label for="review-rating" class="block text-sm font-medium">Số sao</label><select id="review-rating" name="rating" class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3" required>@foreach(range(5, 1) as $rating)<option value="{{ $rating }}" @selected((int)old('rating', $ownReview['rating'] ?? 5) === $rating)>{{ $rating }} sao</option>@endforeach</select></div>
                        <x-ui.input id="review-title" name="title" label="Tiêu đề" :value="old('title', $ownReview['title'] ?? '')" required />
                        <div class="sm:col-span-2"><label for="review-body" class="block text-sm font-medium">Nội dung</label><textarea id="review-body" name="body" minlength="20" maxlength="5000" rows="5" class="mt-2 w-full rounded-control border border-line bg-surface p-3" required>{{ old('body', $ownReview['body'] ?? '') }}</textarea></div>
                        <div class="sm:col-span-2"><x-ui.button type="submit" icon="star">Gửi đánh giá</x-ui.button></div>
                    </form>
                </x-ui.card>
            @else
                <x-ui.alert class="mt-5" tone="success" title="Đánh giá của bạn đã được công bố">Đánh giá đã duyệt được khóa để bảo toàn bằng chứng kiểm duyệt.</x-ui.alert>
            @endif
        @else
            <div class="mt-5"><x-ui.button :href="route('login')" variant="secondary" icon="star">Đăng nhập để đánh giá</x-ui.button></div>
        @endauth
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            @forelse($productReviews as $review)
                <article class="rounded-panel border border-line bg-surface p-5"><p class="font-bold text-warning">{{ str_repeat('★', $review['rating']) }}<span class="sr-only">{{ $review['rating'] }} trên 5 sao</span></p><h3 class="mt-2 font-bold">{{ $review['title'] }}</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-muted">{{ $review['body'] }}</p><p class="mt-4 text-xs text-ink-muted">{{ $review['customer'] }} · Mua hàng đã xác minh</p></article>
            @empty
                <x-ui.empty-state title="Chưa có đánh giá đã duyệt" description="Đánh giá từ khách mua hàng đã xác minh sẽ xuất hiện sau kiểm duyệt." />
            @endforelse
        </div>
    </section>

    @if(count($relatedProducts) > 0)
        <section class="mt-16 border-t border-line pt-12" aria-labelledby="related-products-title">
            <div class="flex items-end justify-between gap-4">
                <div><p class="text-sm font-bold uppercase tracking-[0.16em] text-brand">Cùng danh mục</p><h2 id="related-products-title" class="mt-2 text-2xl font-bold">Sản phẩm liên quan</h2></div>
                <a href="{{ route('public.category', $product->category->slug) }}" class="hidden text-sm font-semibold text-brand hover:underline sm:block">Xem tất cả</a>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($relatedProducts as $related)
                    <article class="group overflow-hidden rounded-panel border border-line bg-surface transition duration-300 hover:-translate-y-1 hover:shadow-panel">
                        <a href="{{ route('public.product', $related->slug) }}" class="block aspect-square overflow-hidden bg-white p-5">
                            @if(isset($related->images[0]))<img src="{{ $related->images[0]->url }}" alt="{{ $related->images[0]->alt }}" width="{{ $related->images[0]->width }}" height="{{ $related->images[0]->height }}" loading="lazy" decoding="async" class="h-full w-full object-contain transition duration-500 group-hover:scale-110">@endif
                        </a>
                        <div class="p-4"><p class="text-xs font-bold uppercase tracking-wider text-brand">{{ $related->category->name }}</p><h3 class="mt-2 font-semibold leading-6"><a href="{{ route('public.product', $related->slug) }}" class="hover:text-brand">{{ $related->name }}</a></h3><p class="mt-3 text-sm text-ink-muted">{{ count($related->variants) }} cấu hình đang công bố</p></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection
