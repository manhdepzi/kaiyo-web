@extends('layouts.admin')
@section('title','CMS — Kaiyo Admin')
@section('content')
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Content</p><h1 class="mt-2 text-3xl font-bold">CMS Workspace</h1><p class="mt-2 text-ink-muted">Mỗi loại nội dung có root/revision riêng. Manage tạo draft; Publish là quyền tác động cao độc lập.</p>
    @if(session('status'))<x-ui.alert class="mt-6" tone="success">{{ session('status') }}</x-ui.alert>@endif @if($errors->any())<x-ui.alert class="mt-6" tone="danger">{{ $errors->first() }}</x-ui.alert>@endif
    <div class="mt-6"><x-ui.button :href="route('admin.pages')" variant="secondary" icon="document-text">Quản lý Page và lịch xuất bản</x-ui.button></div>

    <div class="mt-10 grid gap-8 xl:grid-cols-2">
        <x-ui.card title="Article"><form method="POST" action="{{ route('admin.content.articles.store') }}" class="grid gap-3">@csrf<x-ui.input name="title" label="Tiêu đề" required /><x-ui.input name="slug" label="Slug" required /><x-ui.input name="excerpt" label="Tóm tắt" /><textarea name="body_markdown" aria-label="Nội dung Article" rows="7" required class="rounded-control border border-line bg-surface px-3 py-2" placeholder="Markdown"></textarea><x-ui.button type="submit" icon="document-plus">Tạo draft</x-ui.button></form>@include('admin.partials.content-items',['items'=>$content['articles'],'type'=>'articles','publishRoute'=>'admin.content.articles.publish','reviseRoute'=>'admin.content.articles.revise'])</x-ui.card>
        <x-ui.card title="FAQ"><form method="POST" action="{{ route('admin.content.faqs.store') }}" class="grid gap-3">@csrf<x-ui.input name="question" label="Câu hỏi" required /><x-ui.input name="code" label="Mã" required /><x-ui.input name="position" type="number" label="Thứ tự" value="0" required /><textarea name="answer_markdown" aria-label="Câu trả lời FAQ" rows="7" required class="rounded-control border border-line bg-surface px-3 py-2" placeholder="Markdown"></textarea><x-ui.button type="submit" icon="document-plus">Tạo draft</x-ui.button></form>@include('admin.partials.content-items',['items'=>$content['faqs'],'type'=>'faqs','publishRoute'=>'admin.content.faqs.publish','reviseRoute'=>'admin.content.faqs.revise'])</x-ui.card>
        <x-ui.card title="Slideshow trang chủ">
            <p class="mb-4 text-sm text-ink-muted">Mỗi Banner đã xuất bản tại placement <code>home.hero</code> là một slide. Trang chủ tự chuyển sau 3 giây; thứ tự nhỏ hiển thị trước.</p>
            <form method="POST" action="{{ route('admin.content.banners.store') }}" class="grid gap-3">@csrf
                <x-ui.input name="headline" label="Tên mô tả slide" required />
                <x-ui.input name="code" label="Mã duy nhất" required />
                <input type="hidden" name="placement" value="home.hero">
                <label class="grid gap-1 text-sm font-medium">Ảnh thiết kế
                    <select name="image_path" required class="rounded-control border border-line bg-surface px-3 py-2">
                        @foreach($slideImages as $path => $label)<option value="{{ $path }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <x-ui.input name="sort_order" type="number" label="Thứ tự" value="10" required />
                <x-ui.input name="body" label="Nội dung dự phòng" />
                <x-ui.input name="cta_label" label="CTA label" value="Liên hệ ngay" />
                <x-ui.input name="cta_url" label="CTA URL (HTTPS hoặc /path)" value="/lien-he" />
                <x-ui.button type="submit" icon="photo">Tạo slide nháp</x-ui.button>
            </form>
            @include('admin.partials.content-items',['items'=>$content['banners'],'type'=>'banners','publishRoute'=>'admin.content.banners.publish','reviseRoute'=>'admin.content.banners.revise'])
        </x-ui.card>
        <x-ui.card title="Email Template"><form method="POST" action="{{ route('admin.content.email-templates.store') }}" class="grid gap-3">@csrf<x-ui.input name="template_key" label="Template key" required /><x-ui.input name="subject" label="Subject" required /><x-ui.input name="allowed_variables" label="Biến cho phép, phân cách dấu phẩy" /><textarea name="body_markdown" aria-label="Nội dung Email Template" rows="7" required class="rounded-control border border-line bg-surface px-3 py-2" placeholder="Markdown với @{{ variable }}"></textarea><x-ui.button type="submit" icon="document-plus">Tạo draft</x-ui.button></form>@include('admin.partials.content-items',['items'=>$content['email_templates'],'type'=>'email-templates','publishRoute'=>'admin.content.email-templates.publish','reviseRoute'=>'admin.content.email-templates.revise'])</x-ui.card>
    </div>
</section>
@endsection
