@extends('layouts.public')

@section('title', 'Liên hệ — Kaiyo')
@section('meta_description', 'Liên hệ Kaiyo về sản phẩm, dự án, hỗ trợ kỹ thuật hoặc yêu cầu báo giá doanh nghiệp.')

@section('content')
<section class="mx-auto max-w-6xl px-5 py-12 lg:px-8 lg:py-16">
    <div class="grid gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-brand">Liên hệ Kaiyo</p>
            <h1 class="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">Cùng tìm giải pháp phù hợp cho công trình</h1>
            <p class="mt-6 text-lg leading-8 text-ink-muted">Gửi nhu cầu về sản phẩm, báo giá, dự án hoặc hỗ trợ kỹ thuật. Thông tin sẽ được chuyển thành một yêu cầu CRM để đội ngũ phụ trách tiếp nhận.</p>
            <div class="mt-8 space-y-4">
                <div class="flex gap-4 rounded-card border border-line bg-surface p-5">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand"><x-heroicon-o-chat-bubble-left-right class="size-6" aria-hidden="true" /></span>
                    <div><h2 class="font-semibold">Đúng bộ phận</h2><p class="mt-1 text-sm leading-6 text-ink-muted">Chủ đề bạn chọn giúp yêu cầu được phân loại ngay khi tiếp nhận.</p></div>
                </div>
                <div class="flex gap-4 rounded-card border border-line bg-surface p-5">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand"><x-heroicon-o-shield-check class="size-6" aria-hidden="true" /></span>
                    <div><h2 class="font-semibold">Bảo vệ thông tin</h2><p class="mt-1 text-sm leading-6 text-ink-muted">Dữ liệu chỉ được dùng để xử lý yêu cầu và được kiểm soát quyền truy cập phía máy chủ.</p></div>
                </div>
            </div>
        </div>

        <x-ui.card title="Gửi yêu cầu" description="Các trường có dấu * là bắt buộc. Cần ít nhất email hoặc số điện thoại.">
            @if(session('status'))
                <x-ui.alert class="mb-6" tone="success" title="Đã gửi yêu cầu">{{ session('status') }}</x-ui.alert>
            @endif
            @if($errors->any())
                <x-ui.alert class="mb-6" tone="danger" title="Vui lòng kiểm tra lại">
                    <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif

            <form method="POST" action="{{ route('public.contact.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="operation_key" value="{{ old('operation_key', (string) \Illuminate\Support\Str::ulid()) }}">
                <div class="hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="name" label="Họ và tên *" :value="old('name')" required autocomplete="name" />
                    <x-ui.input name="company_name" label="Công ty" :value="old('company_name')" autocomplete="organization" />
                    <x-ui.input name="email" type="email" label="Email" :value="old('email')" autocomplete="email" />
                    <x-ui.input name="phone" type="tel" label="Số điện thoại" :value="old('phone')" autocomplete="tel" />
                </div>
                <div>
                    <label for="topic" class="block text-sm font-medium">Chủ đề *</label>
                    <select id="topic" name="topic" required class="mt-2 min-h-11 w-full rounded-control border border-line bg-surface px-3 py-2">
                        <option value="">Chọn chủ đề</option>
                        @foreach(['product' => 'Tư vấn sản phẩm', 'quotation' => 'Yêu cầu báo giá', 'project' => 'Trao đổi dự án', 'support' => 'Hỗ trợ kỹ thuật', 'other' => 'Nội dung khác'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('topic') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="message" class="block text-sm font-medium">Nội dung yêu cầu *</label>
                    <textarea id="message" name="message" rows="7" minlength="20" maxlength="4000" required class="mt-2 w-full rounded-control border border-line bg-surface px-3 py-2" placeholder="Mô tả sản phẩm, quy mô hoặc vấn đề bạn cần hỗ trợ...">{{ old('message') }}</textarea>
                    <p class="mt-2 text-xs text-ink-muted">Từ 20 đến 4.000 ký tự.</p>
                </div>
                <label class="flex items-start gap-3 text-sm leading-6">
                    <input type="checkbox" name="privacy_accepted" value="1" required @checked(old('privacy_accepted')) class="mt-1 rounded border-line text-brand focus:ring-brand">
                    <span>Tôi đồng ý để Kaiyo sử dụng thông tin trên nhằm tiếp nhận và xử lý yêu cầu này.</span>
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button type="submit" icon="paper-airplane">Gửi yêu cầu</x-ui.button>
                    <x-ui.button :href="route('public.search')" variant="ghost" icon="magnifying-glass">Xem sản phẩm</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</section>
@endsection
