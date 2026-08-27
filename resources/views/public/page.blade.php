@extends('layouts.public')
@section('title', $page->title.' — Kaiyo')
@section('meta_description', $page->summary ?? $page->title)
@section('content')
<article class="mx-auto max-w-4xl px-5 py-12 lg:px-8"><p class="text-sm font-semibold uppercase tracking-widest text-brand">Nội dung</p><h1 class="mt-3 text-4xl font-bold">{{ $page->title }}</h1>@if($page->summary)<p class="mt-4 text-lg text-ink-muted">{{ $page->summary }}</p>@endif<div class="prose prose-slate mt-8 max-w-none">{!! $page->sanitizedBodyHtml !!}</div></article>
@endsection
