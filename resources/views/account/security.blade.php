@extends('layouts.auth')
@section('title', 'Bảo mật tài khoản')
@section('heading', 'Bảo mật tài khoản')
@section('content')
<section aria-labelledby="two-factor-heading">
    <h2 id="two-factor-heading" class="font-semibold">Xác thực hai lớp</h2>

    @if (! $account->two_factor_secret)
        <p class="mt-2 text-sm text-slate-400">Dùng ứng dụng tạo mã TOTP để bảo vệ tài khoản.</p>
        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
            @csrf
            <button class="w-full rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950">Bật xác thực hai lớp</button>
        </form>
    @elseif (! $account->hasEnabledTwoFactorAuthentication())
        <p class="mt-2 text-sm text-amber-300">Quét mã QR rồi nhập mã hiện tại để hoàn tất.</p>
        <div class="mt-4 rounded-lg bg-white p-4">{!! $account->twoFactorQrCodeSvg() !!}</div>
        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 space-y-3">
            @csrf
            <label class="block text-sm" for="code">Mã xác thực</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
            <button class="w-full rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950">Xác nhận</button>
        </form>
    @else
        <p class="mt-2 text-sm text-emerald-300">Xác thực hai lớp đang hoạt động.</p>
        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-4">
            @csrf
            <button class="w-full rounded-lg border border-slate-700 px-4 py-2">Tạo lại mã khôi phục</button>
        </form>
        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
            @csrf
            @method('DELETE')
            <button class="w-full rounded-lg border border-red-800 px-4 py-2 text-red-300">Tắt xác thực hai lớp</button>
        </form>
    @endif
</section>

<section aria-labelledby="sessions-heading" class="mt-8 border-t border-slate-800 pt-6">
    <h2 id="sessions-heading" class="font-semibold">Phiên đang hoạt động</h2>
    <div class="mt-4 space-y-3">
        @forelse ($sessions as $session)
            <article class="rounded-lg border border-slate-800 p-3 text-sm">
                <p class="break-words text-slate-300">{{ $session->user_agent_redacted ?? 'Thiết bị không xác định' }}</p>
                <p class="mt-1 text-slate-500">Hoạt động: {{ $session->last_seen_at->format('Y-m-d H:i:s T') }}</p>
                <form method="POST" action="{{ route('account.security.sessions.destroy', $session->public_id) }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-md border border-red-900 px-3 py-1 text-red-300">Thu hồi phiên</button>
                </form>
            </article>
        @empty
            <p class="text-sm text-slate-400">Chưa có phiên nào được ghi nhận.</p>
        @endforelse
    </div>
</section>

<a href="{{ route('account') }}" class="mt-6 block text-center text-sm text-cyan-400">Quay lại tài khoản</a>
@endsection
