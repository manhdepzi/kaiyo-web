@extends('layouts.auth')
@section('title', 'Xác nhận mật khẩu')
@section('heading', 'Xác nhận mật khẩu')
@section('content')
<form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
    @csrf
    <x-ui.input name="password" label="Mật khẩu" type="password" required autofocus autocomplete="current-password" />
    <x-ui.button type="submit" class="w-full" icon="check-circle">Xác nhận</x-ui.button>
</form>
@endsection
