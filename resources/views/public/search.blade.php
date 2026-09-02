@extends('layouts.public')

@section('title', ($term !== '' ? 'Kết quả cho “'.$term.'”' : 'Danh mục sản phẩm').' — Kaiyo')
@section('meta_description', 'Tìm kiếm danh mục sản phẩm Kaiyo theo tên hoặc SKU.')
@section('robots', 'noindex,follow')
@section('canonical', route('public.search'))

@push('head')
    @php
        $schemaContextKey = chr(64).'context';
        $schemaTypeKey = chr(64).'type';
        $searchItems = array_map(static fn ($hit, $index): array => [$schemaTypeKey => 'ListItem', 'position' => $index + 1, 'url' => route('public.product', $hit->slug), 'name' => $hit->productName], $result->hits, array_keys($result->hits));
        $searchBreadcrumb = [$schemaContextKey => 'https://schema.org', $schemaTypeKey => 'BreadcrumbList', 'itemListElement' => [[$schemaTypeKey => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')], [$schemaTypeKey => 'ListItem', 'position' => 2, 'name' => 'Sản phẩm', 'item' => route('public.search')]]];
        $searchItemList = [$schemaContextKey => 'https://schema.org', $schemaTypeKey => 'ItemList', 'itemListElement' => $searchItems];
    @endphp
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $term !== '' ? 'Kết quả tìm kiếm: '.$term : 'Danh mục sản phẩm' }} — Kaiyo">
    <meta property="og:description" content="Tìm kiếm sản phẩm Kaiyo theo tên hoặc SKU.">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $term !== '' ? 'Kết quả tìm kiếm: '.$term : 'Danh mục sản phẩm' }} — Kaiyo">
    <script type="application/ld+json">{!! json_encode($searchBreadcrumb, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) !!}</script>
    <script type="application/ld+json">{!! json_encode($searchItemList, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
<section class="mx-auto max-w-7xl px-5 py-12 lg:px-8">
    <div class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-widest text-brand">Danh mục</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $term !== '' ? 'Kết quả tìm kiếm' : 'Tất cả sản phẩm' }}</h1>
        <form action="{{ route('public.search') }}" method="GET" role="search" class="mt-7 flex flex-col gap-3 sm:flex-row">
            <div class="flex-1"><x-ui.input name="q" label="Tên sản phẩm hoặc SKU" type="search" :value="$term" maxlength="100" /></div>
            <x-ui.button type="submit" class="self-end" icon="magnifying-glass">Tìm kiếm</x-ui.button>
            @if ($term !== '')
                <a href="{{ route('public.search') }}" class="inline-flex min-h-10 items-center gap-2 self-start rounded-control px-3 text-sm font-semibold text-ink-muted transition hover:bg-surface-muted hover:text-brand sm:self-end"><x-heroicon-o-x-mark class="size-4" aria-hidden="true" />Xóa từ khóa</a>
            @endif
        </form>
    </div>

    @if (count($result->hits) === 0)
        <x-ui.empty-state class="mt-10" title="Không tìm thấy sản phẩm" description="Hãy kiểm tra chính tả, thử SKU khác hoặc dùng từ khóa ngắn hơn.">
            @if ($term !== '')<x-ui.button :href="route('public.search')" variant="secondary" icon="x-mark">Xóa tìm kiếm</x-ui.button>@endif
        </x-ui.empty-state>
    @else
        <p class="mt-10 text-sm text-ink-muted" role="status">Trang {{ $result->page }} · Hiển thị {{ count($result->hits) }} kết quả</p>
        <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-live="polite">
            @foreach ($result->hits as $hit)
                <x-public.product-hit :hit="$hit" />
            @endforeach
        </div>
        <nav class="mt-8 flex items-center justify-between" aria-label="Phân trang kết quả">
            @if ($result->page > 1)
                <x-ui.button :href="route('public.search', ['q' => $term, 'page' => $result->page - 1])" variant="secondary" icon="chevron-left">Trang trước</x-ui.button>
            @else
                <span></span>
            @endif
            @if ($result->hasMore)
                <x-ui.button :href="route('public.search', ['q' => $term, 'page' => $result->page + 1])" variant="secondary" icon="chevron-right" icon-position="end">Trang sau</x-ui.button>
            @endif
        </nav>
    @endif
</section>
@endsection
