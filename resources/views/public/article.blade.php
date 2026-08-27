@extends('layouts.public')
@section('title', $article->title.' — Kaiyo')
@section('content')
<article class="mx-auto max-w-3xl px-5 py-12 lg:py-16"><p class="text-sm font-semibold uppercase tracking-widest text-brand">Bài viết</p><h1 class="mt-3 text-4xl font-bold tracking-tight">{{ $article->title }}</h1>@if($article->excerpt)<p class="mt-4 text-lg text-ink-muted">{{ $article->excerpt }}</p>@endif<time class="mt-4 block text-sm text-ink-muted" datetime="{{ $article->publishedAt }}">{{ $article->publishedAt }}</time><div class="mt-10 space-y-5 leading-8">{!! $article->sanitizedBodyHtml !!}</div></article>
@endsection
