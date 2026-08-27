<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Sales — Kaiyo')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <a href="#staff-content" class="sr-only z-50 rounded-control bg-brand px-4 py-3 text-on-brand focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Đi tới nội dung chính</a>
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-4 px-5 py-4 lg:px-8">
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="font-bold tracking-[0.18em] text-brand">KAIYO</a>
                <span class="text-sm font-semibold text-ink-muted">Sales Workspace</span>
            </div>
            <nav class="flex items-center gap-2" aria-label="Điều hướng nhân viên">
                @if ($staffNavigation['customers'] ?? false)<x-ui.button :href="route('sales.customers')" variant="secondary" size="sm">Khách hàng</x-ui.button>@endif
                @if ($staffNavigation['leads'] ?? false)<x-ui.button :href="route('sales.leads')" variant="secondary" size="sm">Lead</x-ui.button>@endif
                @if ($staffNavigation['companies'] ?? false)<x-ui.button :href="route('sales.companies')" variant="secondary" size="sm">Công ty</x-ui.button>@endif
                @if ($staffNavigation['quotes'] ?? false)<x-ui.button :href="route('sales.quotes')" variant="secondary" size="sm">Báo giá</x-ui.button>@endif
                @if ($staffNavigation['orders'] ?? false)<x-ui.button :href="route('sales.orders')" variant="secondary" size="sm">Đơn hàng</x-ui.button>@endif
                <x-ui.button :href="route('account.security')" variant="ghost" size="sm">Bảo mật</x-ui.button>
                <form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="ghost" size="sm">Đăng xuất</x-ui.button></form>
            </nav>
        </div>
    </header>
    <main id="staff-content">@yield('content')</main>
</body>
</html>
