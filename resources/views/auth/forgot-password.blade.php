@extends('layouts.auth')
@section('title', 'Khôi phục mật khẩu')
@section('heading', 'Khôi phục mật khẩu')
@section('content')
<p class="mb-5 text-sm text-ink-muted">Nếu tài khoản tồn tại, hệ thống sẽ gửi hướng dẫn đặt lại mật khẩu.</p>
<form method="POST" action="{{ route('password.email') }}" class="space-y-5">
    @csrf
    <x-ui.input name="email_normalized" label="Email" type="email" required autofocus autocomplete="email" />
    <x-ui.button type="submit" class="w-full">Gửi hướng dẫn</x-ui.button>
</form>
@endsection
