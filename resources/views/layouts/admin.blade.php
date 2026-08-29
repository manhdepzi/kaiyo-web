<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin — Kaiyo')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <a href="#admin-content" class="sr-only focus:not-sr-only">Đi tới nội dung chính</a>
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-4 px-5 py-4 lg:px-8">
            <span>
                <a href="{{ route('home') }}" class="font-bold tracking-[0.18em] text-brand">KAIYO</a>
                <small class="ml-4 text-ink-muted">Admin Workspace</small>
            </span>
            <nav class="flex flex-wrap items-center gap-2" aria-label="Điều hướng quản trị">
                @if($adminNavigation['catalog'] ?? false)<x-ui.button :href="route('admin.catalog')" variant="secondary" size="sm" icon="squares-2x2">Sản phẩm</x-ui.button>@endif
                @if($adminNavigation['content'] ?? false)<x-ui.button :href="route('admin.content')" variant="secondary" size="sm" icon="document-text">Nội dung</x-ui.button>@endif
                @if($adminNavigation['merchant'] ?? false)<x-ui.button :href="route('admin.merchant')" variant="secondary" size="sm" icon="shopping-bag">Merchant</x-ui.button>@endif
                @if($adminNavigation['analytics'] ?? false)<x-ui.button :href="route('admin.analytics')" variant="secondary" size="sm" icon="chart-bar-square">Analytics</x-ui.button>@endif
                @if($adminNavigation['outbox'] ?? false)<x-ui.button :href="route('admin.outbox')" variant="secondary" size="sm" icon="queue-list">Outbox</x-ui.button>@endif
                @if($adminNavigation['audit'] ?? false)<x-ui.button :href="route('admin.audit')" variant="secondary" size="sm" icon="shield-check">Audit</x-ui.button>@endif
                <x-ui.button :href="route('account.security')" variant="ghost" size="sm" icon="lock-closed">Bảo mật</x-ui.button>
            </nav>
        </div>
    </header>
    <main id="admin-content">@yield('content')</main>
</body>
</html>
