@extends('layouts.auth')
@section('title', 'Xác minh email')
@section('heading', 'Xác minh email')
@section('content')
<p class="text-sm text-ink-muted">Hãy mở liên kết xác minh đã gửi tới email của bạn trước khi sử dụng tài khoản.</p>
<form method="POST" action="{{ route('verification.send') }}" class="mt-5">
    @csrf
    <x-ui.button type="submit" class="w-full" icon="paper-airplane">Gửi lại liên kết</x-ui.button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-3">
    @csrf
    <x-ui.button type="submit" variant="secondary" class="w-full" icon="arrow-right-start-on-rectangle">Đăng xuất</x-ui.button>
</form>
@endsection
