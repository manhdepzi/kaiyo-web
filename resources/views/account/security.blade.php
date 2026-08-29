@extends('layouts.public')

@section('title', 'Bảo mật tài khoản — Kaiyo')
@section('meta_description', 'Quản lý xác thực hai lớp và các phiên đăng nhập tài khoản Kaiyo.')

@section('content')
<section class="mx-auto max-w-5xl px-5 py-12 lg:px-8">
    <a href="{{ route('account') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:underline"><x-heroicon-o-arrow-left class="size-4" aria-hidden="true" />Quay lại tài khoản</a>
    <div class="mt-5 max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-widest text-brand">Customer Portal</p>
        <h1 class="mt-3 text-3xl font-bold">Bảo mật tài khoản</h1>
        <p class="mt-2 text-ink-muted">Bảo vệ tài khoản bằng TOTP và thu hồi ngay các phiên không còn tin cậy.</p>
    </div>

    @if (session('status'))
        <x-ui.alert class="mt-6" tone="success" title="Đã cập nhật">{{ session('status') }}</x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert class="mt-6" tone="danger" title="Không thể hoàn tất yêu cầu">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_1.15fr]">
        <x-ui.card title="Xác thực hai lớp" description="Mã TOTP bổ sung một lớp bảo vệ ngoài mật khẩu.">
            @if (! $account->two_factor_secret)
                <p class="text-sm text-ink-muted">Dùng ứng dụng tạo mã TOTP để bảo vệ tài khoản.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-5">
                    @csrf
                    <x-ui.button type="submit" class="w-full" icon="shield-check">Bật xác thực hai lớp</x-ui.button>
                </form>
            @elseif (! $account->hasEnabledTwoFactorAuthentication())
                <x-ui.alert tone="warning" title="Cần xác nhận">Quét mã QR rồi nhập mã hiện tại để hoàn tất thiết lập.</x-ui.alert>
                <div class="mx-auto mt-5 max-w-72 rounded-control border border-line bg-white p-4" aria-label="Mã QR xác thực hai lớp">{!! $account->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-5 space-y-4">
                    @csrf
                    <x-ui.input name="code" label="Mã xác thực" inputmode="numeric" autocomplete="one-time-code" required />
                    <x-ui.button type="submit" class="w-full" icon="check-circle">Xác nhận</x-ui.button>
                </form>
            @else
                <x-ui.alert tone="success" title="Đang hoạt động">Xác thực hai lớp đã được bật cho tài khoản này.</x-ui.alert>
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-5">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" class="w-full" icon="arrow-path">Tạo lại mã khôi phục</x-ui.button>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger" class="w-full" icon="shield-exclamation">Tắt xác thực hai lớp</x-ui.button>
                </form>
            @endif
        </x-ui.card>

        <x-ui.card title="Phiên đang hoạt động" description="Thu hồi phiên sẽ vô hiệu hóa phiên đó ngay lập tức.">
            <div class="space-y-3">
                @forelse ($sessions as $session)
                    <article class="rounded-control border border-line bg-surface-muted p-4 text-sm">
                        <p class="break-words font-medium text-ink">{{ $session->user_agent_redacted ?? 'Thiết bị không xác định' }}</p>
                        <p class="mt-1 text-ink-muted">Hoạt động: <time datetime="{{ $session->last_seen_at->toAtomString() }}">{{ $session->last_seen_at->format('Y-m-d H:i:s T') }}</time></p>
                        <form method="POST" action="{{ route('account.security.sessions.destroy', $session->public_id) }}" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" size="sm" icon="x-circle">Thu hồi phiên</x-ui.button>
                        </form>
                    </article>
                @empty
                    <x-ui.empty-state title="Chưa có phiên nào" description="Phiên đăng nhập được nhận diện sẽ xuất hiện tại đây." />
                @endforelse
            </div>
        </x-ui.card>
    </div>
</section>
@endsection
