@extends('layouts.auth')
@section('title', 'Đăng nhập')
@section('heading', 'Đăng nhập')
@section('content')
<form method="POST" action="{{ route('login.store') }}" class="space-y-5">
    @csrf
    <x-ui.input name="email_normalized" label="Email" type="email" required autofocus autocomplete="username" />
    <x-ui.input name="password" label="Mật khẩu" type="password" required autocomplete="current-password" />
    <label class="flex min-h-11 items-center gap-3 text-sm text-ink">
        <input name="remember" type="checkbox" value="1" class="size-5 rounded border-line bg-surface text-brand">
        Ghi nhớ đăng nhập
    </label>
    <x-ui.button type="submit" class="w-full">Đăng nhập</x-ui.button>
</form>
<div class="mt-5 flex justify-between gap-4 text-sm">
    <a class="text-brand hover:underline" href="{{ route('password.request') }}">Quên mật khẩu?</a>
    <a class="text-brand hover:underline" href="{{ route('register') }}">Tạo tài khoản</a>
</div>
@endsection
