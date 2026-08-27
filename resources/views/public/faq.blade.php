@extends('layouts.public')
@section('title','Câu hỏi thường gặp — Kaiyo')
@section('content')
<section class="mx-auto max-w-4xl px-5 py-12 lg:py-16"><p class="text-sm font-semibold uppercase tracking-widest text-brand">Hỗ trợ</p><h1 class="mt-3 text-4xl font-bold tracking-tight">Câu hỏi thường gặp</h1><div class="mt-10 space-y-4">@forelse($directory->items as $faq)<details id="{{ $faq['code'] }}" class="rounded-control border border-line bg-surface p-5"><summary class="cursor-pointer font-semibold">{{ $faq['question'] }}</summary><div class="mt-4 space-y-4 leading-7">{!! $faq['sanitized_answer_html'] !!}</div></details>@empty<x-ui.empty-state title="Chưa có câu hỏi" description="Nội dung hỗ trợ đang được cập nhật." />@endforelse</div></section>
@endsection
