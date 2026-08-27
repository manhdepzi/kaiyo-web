@props(['hit'])

<article {{ $attributes->class('rounded-panel border border-line bg-surface p-5 shadow-sm') }}>
    <x-ui.badge>{{ $hit->sku }}</x-ui.badge>
    <h2 class="mt-4 text-lg font-semibold"><a class="hover:text-brand hover:underline" href="{{ route('public.product', $hit->slug) }}">{{ $hit->productName }}</a></h2>
    <p class="mt-1 text-sm text-ink-muted">{{ $hit->variantName }}</p>
    <p class="mt-5 text-sm font-medium text-brand">Giá và tồn kho được xác nhận khi chọn mua hoặc yêu cầu báo giá.</p>
</article>
