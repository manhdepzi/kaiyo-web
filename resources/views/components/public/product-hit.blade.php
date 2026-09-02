@props(['hit'])
@inject('presentations', 'App\Modules\Catalog\Application\Support\ProductPresentationCatalog')
@php($image = $presentations->primaryFor($hit->slug, $hit->productName))

<article {{ $attributes->class('group overflow-hidden rounded-panel border border-line bg-surface shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-panel') }}>
    <a href="{{ route('public.product', $hit->slug) }}" class="block aspect-[4/3] overflow-hidden bg-white" tabindex="-1" aria-hidden="true">
        @if ($image)
            <img src="{{ $image->url }}" alt="{{ $hit->productName }}" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy" decoding="async" class="h-full w-full object-contain p-5 transition duration-500 ease-out group-hover:scale-105">
        @else
            <span class="grid h-full place-items-center text-sm text-ink-muted">Ảnh đang cập nhật</span>
        @endif
    </a>
    <div class="p-5">
    <x-ui.badge>{{ $hit->sku }}</x-ui.badge>
    <h2 class="mt-4 text-lg font-semibold"><a class="hover:text-brand hover:underline" href="{{ route('public.product', $hit->slug) }}">{{ $hit->productName }}</a></h2>
    <p class="mt-1 text-sm text-ink-muted">{{ $hit->variantName }}</p>
    <p class="mt-5 text-sm font-medium text-brand">Giá và tồn kho được xác nhận khi chọn mua hoặc yêu cầu báo giá.</p>
        <a href="{{ route('public.product', $hit->slug) }}" class="mt-4 inline-flex min-h-10 items-center gap-2 rounded-control border border-line px-3 text-sm font-semibold text-ink transition hover:border-brand hover:text-brand">Xem chi tiết <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
    </div>
</article>
