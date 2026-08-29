@extends('layouts.auth')
@section('title', 'Đặt lại mật khẩu')
@section('heading', 'Đặt lại mật khẩu')
@section('content')
<form method="POST" action="{{ route('password.store') }}" class="space-y-5">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <x-ui.input name="email_normalized" label="Email" type="email" :value="$request->email_normalized" required autocomplete="email" />
    <x-ui.input name="password" label="Mật khẩu mới" type="password" required autocomplete="new-password" />
    <x-ui.input name="password_confirmation" label="Xác nhận mật khẩu" type="password" required autocomplete="new-password" />
    <x-ui.button type="submit" class="w-full" icon="key">Đặt lại mật khẩu</x-ui.button>
</form>
@endsection
