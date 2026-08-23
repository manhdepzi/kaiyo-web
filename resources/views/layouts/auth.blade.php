<!DOCTYPE html>
<html lang="vi" class="theme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') — Kaiyo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-6 py-12">
        <x-ui.card class="w-full" aria-labelledby="auth-heading">
            <a href="{{ route('home') }}" class="text-sm font-semibold tracking-widest text-brand">KAIYO</a>
            <h1 id="auth-heading" class="mt-5 text-2xl font-semibold text-ink">@yield('heading')</h1>

            @if (session('status'))
                <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
            @endif

            @if ($errors->any())
                <x-ui.alert tone="danger" class="mt-4" title="Không thể hoàn tất yêu cầu">
                    Vui lòng kiểm tra lại thông tin trong biểu mẫu.
                </x-ui.alert>
            @endif

            <div class="mt-6">@yield('content')</div>
        </x-ui.card>
    </main>
</body>
</html>
