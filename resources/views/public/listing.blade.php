@extends('layouts.public')

@section('title', $heading.' — Kaiyo')
@section('meta_description', $description)
@section('canonical', route($routeName, array_filter(['slug' => $routeSlug, 'page' => $result->page > 1 ? $result->page : null])))
@if ($result->page > 1 && count($result->hits) === 0)
    @section('robots', 'noindex,follow')
@endif
@push('head')
    @if ($result->page > 1)<link rel="prev" href="{{ route($routeName, array_filter(['slug' => $routeSlug, 'page' => $result->page - 1 > 1 ? $result->page - 1 : null])) }}">@endif
    @if ($result->hasMore)<link rel="next" href="{{ route($routeName, ['slug' => $routeSlug, 'page' => $result->page + 1]) }}">@endif
@endpush

@section('content')
<section class="mx-auto max-w-7xl px-5 py-12 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">{{ $eyebrow }}</p>
    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ $heading }}</h1>
    <p class="mt-4 max-w-2xl text-ink-muted">{{ $description }}</p>

    @if (count($result->hits) === 0)
        <x-ui.empty-state class="mt-10" title="Chưa có sản phẩm được công bố" description="Danh sách sẽ cập nhật khi sản phẩm đáp ứng điều kiện xuất bản." />
    @else
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($result->hits as $hit)<x-public.product-hit :hit="$hit" />@endforeach
        </div>
        <nav class="mt-8 flex items-center justify-between" aria-label="Phân trang">
            @if ($result->page > 1)<x-ui.button :href="route($routeName, ['slug' => $routeSlug, 'page' => $result->page - 1])" variant="secondary">Trang trước</x-ui.button>@else<span></span>@endif
            @if ($result->hasMore)<x-ui.button :href="route($routeName, ['slug' => $routeSlug, 'page' => $result->page + 1])" variant="secondary">Trang sau</x-ui.button>@endif
        </nav>
    @endif
</section>
@endsection
