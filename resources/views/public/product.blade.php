@extends('layouts.public')

@section('title', $product->name.' — Kaiyo')
@section('meta_description', $product->description ?: 'Thông tin sản phẩm '.$product->name.' tại Kaiyo.')
@push('head')
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
<article class="mx-auto max-w-7xl px-5 py-12 lg:px-8">
    <nav class="flex flex-wrap gap-2 text-sm text-ink-muted" aria-label="Đường dẫn">
        <a class="hover:text-brand" href="{{ route('home') }}">Trang chủ</a><span aria-hidden="true">/</span>
        <a class="hover:text-brand" href="{{ route('public.category', $product->category->slug) }}">{{ $product->category->name }}</a><span aria-hidden="true">/</span>
        <span aria-current="page">{{ $product->name }}</span>
    </nav>

    <div class="mt-8 grid gap-10 lg:grid-cols-2">
        <div class="flex aspect-square items-center justify-center rounded-panel border border-line bg-surface-muted p-8 text-center text-ink-muted" role="img" aria-label="Hình ảnh sản phẩm chưa được công bố">
            Hình ảnh đang được cập nhật
        </div>
        <div>
            @if ($product->brand)<a class="text-sm font-semibold uppercase tracking-widest text-brand" href="{{ route('public.brand', $product->brand->slug) }}">{{ $product->brand->name }}</a>@endif
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $product->name }}</h1>
            @if ($product->description)<p class="mt-5 whitespace-pre-line leading-7 text-ink-muted">{{ $product->description }}</p>@endif
            <x-ui.alert class="mt-7" tone="info" title="Giá và tồn kho theo thời điểm">Hệ thống sẽ xác nhận dữ liệu thương mại cho biến thể và số lượng cụ thể trước khi tạo cam kết.</x-ui.alert>

            <section class="mt-8" aria-labelledby="variant-heading">
                <h2 id="variant-heading" class="text-lg font-semibold">Biến thể đang bán</h2>
                @if (count($product->variants) === 0)
                    <p class="mt-3 text-sm text-ink-muted">Chưa có biến thể khả dụng.</p>
                @else
                    <ul class="mt-3 divide-y divide-line rounded-panel border border-line bg-surface">
                        @foreach ($product->variants as $variant)
                            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                                <div><p class="font-medium">{{ $variant->name }}</p><p class="mt-1 text-sm text-ink-muted">SKU: {{ $variant->sku }}</p></div>
                                <form method="POST" action="{{ route('public.cart.lines.store') }}" class="flex items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="variant_public_id" value="{{ $variant->publicId }}">
                                    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                    <div>
                                        <label class="block text-xs font-medium text-ink-muted" for="quantity-{{ $variant->publicId }}">Số lượng</label>
                                        <input id="quantity-{{ $variant->publicId }}" name="quantity" value="1" inputmode="decimal" required class="mt-1 min-h-9 w-20 rounded-control border border-line bg-surface px-2 text-ink">
                                    </div>
                                    <x-ui.button type="submit" variant="secondary" size="sm">Thêm vào giỏ</x-ui.button>
                                    <x-ui.button :href="route('public.quotation', ['variant' => $variant->publicId])" variant="ghost" size="sm">Yêu cầu báo giá</x-ui.button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</article>
@endsection
