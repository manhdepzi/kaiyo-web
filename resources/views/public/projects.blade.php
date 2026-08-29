@extends('layouts.public')

@section('title', 'Dự án đã thực hiện — Kaiyo')
@section('meta_description', 'Các dự án tiêu biểu Kaiyo đã cung cấp và lắp đặt hệ thống ống gió, van gió, thông gió, hút khói và điều hòa không khí.')

@section('content')
<section class="overflow-hidden border-b border-line bg-ink text-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 lg:grid-cols-[1fr_auto] lg:items-end lg:px-8 lg:py-24">
        <div class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-cyan-300">Năng lực thực tế</p>
            <h1 class="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">Dự án Kaiyo đã thực hiện</h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">Hồ sơ công trình trong lĩnh vực ống gió, van gió, hút khói, làm mát nhà xưởng và điều hòa không khí.</p>
        </div>
        <div class="rounded-panel border border-white/20 bg-white/10 px-8 py-6 text-center backdrop-blur">
            <p class="text-4xl font-bold text-cyan-300">{{ $projectCount }}</p>
            <p class="mt-1 text-sm text-slate-300">hồ sơ dự án và công trình</p>
        </div>
    </div>
</section>

<nav class="sticky top-0 z-10 border-b border-line bg-surface/95 backdrop-blur" aria-label="Nhóm dự án">
    <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-5 py-3 lg:px-8">
        <a href="#tieu-bieu-2025" class="whitespace-nowrap rounded-full bg-brand px-4 py-2 text-sm font-semibold text-on-brand">Tiêu biểu 2025</a>
        <a href="#tieu-bieu-2024" class="whitespace-nowrap rounded-full border border-line px-4 py-2 text-sm font-semibold hover:border-brand hover:text-brand">Tiêu biểu 2024</a>
        <a href="#du-an-khac" class="whitespace-nowrap rounded-full border border-line px-4 py-2 text-sm font-semibold hover:border-brand hover:text-brand">Dự án khác</a>
    </div>
</nav>

<section id="tieu-bieu-2025" class="mx-auto max-w-7xl scroll-mt-20 px-5 py-16 lg:px-8" aria-labelledby="title-2025">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Công trình mới</p>
    <h2 id="title-2025" class="mt-3 text-3xl font-bold tracking-tight">Dự án tiêu biểu 2025</h2>
    <div class="mt-9 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($featured2025 as $project)
            <x-public.project-card :project="$project" :priority="$loop->first" @class(['lg:col-span-2' => $loop->first]) />
        @endforeach
    </div>
</section>

<section id="tieu-bieu-2024" class="scroll-mt-20 border-y border-line bg-surface-muted" aria-labelledby="title-2024">
    <div class="mx-auto max-w-7xl px-5 py-16 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-widest text-brand">Hồ sơ công trình</p>
        <h2 id="title-2024" class="mt-3 text-3xl font-bold tracking-tight">Dự án tiêu biểu 2024</h2>
        <div class="mt-9 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($featured2024 as $project)<x-public.project-card :project="$project" />@endforeach
        </div>
    </div>
</section>

<section id="du-an-khac" class="mx-auto max-w-7xl scroll-mt-20 px-5 py-16 lg:px-8" aria-labelledby="other-title">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Kinh nghiệm đa lĩnh vực</p>
    <h2 id="other-title" class="mt-3 text-3xl font-bold tracking-tight">Một số dự án trọng điểm khác</h2>
    <div class="mt-9 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($otherProjects as $project)<x-public.project-card :project="$project" />@endforeach
    </div>
</section>
@endsection
