@extends('layouts.auth')
@section('title', 'Xác thực hai lớp')
@section('heading', 'Xác thực hai lớp')
@section('content')
<form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
    @csrf
    <x-ui.input name="code" label="Mã xác thực" inputmode="numeric" autofocus autocomplete="one-time-code" />
    <x-ui.input name="recovery_code" label="Hoặc mã khôi phục" autocomplete="one-time-code" />
    <x-ui.button type="submit" class="w-full" icon="shield-check">Xác nhận</x-ui.button>
</form>
@endsection
