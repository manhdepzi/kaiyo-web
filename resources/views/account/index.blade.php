@extends('layouts.auth')
@section('title', 'Tài khoản')
@section('heading', 'Tài khoản của bạn')
@section('content')
<p class="text-sm text-slate-300">Email: {{ auth()->user()->email_display }}</p>
<p class="mt-2 text-sm text-slate-400">Trạng thái xác minh và bảo mật phiên được quản lý tại đây.</p>
<a href="{{ route('account.security') }}" class="mt-6 block rounded-lg bg-cyan-500 px-4 py-2 text-center font-semibold text-slate-950">Quản lý bảo mật</a>
<form method="POST" action="{{ route('logout') }}" class="mt-6">@csrf
    <button class="w-full rounded-lg border border-slate-700 px-4 py-2">Đăng xuất</button>
</form>
@endsection
