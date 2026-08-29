@extends('layouts.public')

@section('title', ($term !== '' ? 'Kết quả cho “'.$term.'”' : 'Danh mục sản phẩm').' — Kaiyo')
@section('meta_description', 'Tìm kiếm danh mục sản phẩm Kaiyo theo tên hoặc SKU.')
@section('robots', 'noindex,follow')
@section('canonical', route('public.search'))

@section('content')
<section class="mx-auto max-w-7xl px-5 py-12 lg:px-8">
    <div class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-widest text-brand">Danh mục</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $term !== '' ? 'Kết quả tìm kiếm' : 'Tất cả sản phẩm' }}</h1>
        <form action="{{ route('public.search') }}" method="GET" role="search" class="mt-7 flex flex-col gap-3 sm:flex-row">
            <div class="flex-1"><x-ui.input name="q" label="Tên sản phẩm hoặc SKU" type="search" :value="$term" maxlength="100" /></div>
            <x-ui.button type="submit" class="self-end" icon="magnifying-glass">Tìm kiếm</x-ui.button>
        </form>
    </div>

    @if (count($result->hits) === 0)
        <x-ui.empty-state class="mt-10" title="Không tìm thấy sản phẩm" description="Hãy kiểm tra chính tả, thử SKU khác hoặc dùng từ khóa ngắn hơn.">
            @if ($term !== '')<x-ui.button :href="route('public.search')" variant="secondary" icon="x-mark">Xóa tìm kiếm</x-ui.button>@endif
        </x-ui.empty-state>
    @else
        <p class="mt-10 text-sm text-ink-muted" role="status">Trang {{ $result->page }} · Hiển thị {{ count($result->hits) }} kết quả</p>
        <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
