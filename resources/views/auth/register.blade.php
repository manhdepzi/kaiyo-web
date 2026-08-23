@extends('layouts.auth')
@section('title', 'Tạo tài khoản')
@section('heading', 'Tạo tài khoản')
@section('content')
<form method="POST" action="{{ route('register.store') }}" class="space-y-5">
    @csrf
    <x-ui.input name="email_normalized" label="Email" type="email" required autofocus autocomplete="email" />
    <x-ui.input name="password" label="Mật khẩu" type="password" required autocomplete="new-password" help="Tối thiểu 12 ký tự, gồm chữ hoa, chữ thường, số và ký hiệu." />
    <x-ui.input name="password_confirmation" label="Xác nhận mật khẩu" type="password" required autocomplete="new-password" />
    <x-ui.button type="submit" class="w-full">Đăng ký</x-ui.button>
</form>
@endsection
