<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', 'Kaiyo — nền tảng tra cứu sản phẩm và yêu cầu báo giá cho khách hàng cá nhân, doanh nghiệp.')">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <title>@yield('title', 'Kaiyo')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <a href="#main-content" class="sr-only z-50 rounded-control bg-brand px-4 py-3 text-on-brand focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Đi tới nội dung chính</a>

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-5 py-4 lg:px-8">
            <a href="{{ route('home') }}" class="shrink-0 rounded-control transition hover:opacity-90" aria-label="Kaiyo — Trang chủ">
                <img src="{{ asset('images/design/logo/logo-kaiyo-wat-1024x560.webp') }}" alt="Kaiyo" width="1024" height="560" class="h-12 w-auto object-contain sm:h-14">
            </a>
            <nav class="hidden items-center gap-7 text-sm font-medium md:flex" aria-label="Điều hướng chính">
                <a class="inline-flex items-center gap-2 transition hover:text-brand" href="{{ route('public.search') }}"><x-heroicon-o-magnifying-glass class="size-4" aria-hidden="true" />Sản phẩm</a>
                <a class="inline-flex items-center gap-2 transition hover:text-brand" href="{{ route('public.projects') }}"><x-heroicon-o-building-office-2 class="size-4" aria-hidden="true" />Dự án</a>
                <a class="inline-flex items-center gap-2 transition hover:text-brand" href="{{ route('public.about') }}"><x-heroicon-o-information-circle class="size-4" aria-hidden="true" />Giới thiệu</a>
                <a class="inline-flex items-center gap-2 transition hover:text-brand" href="{{ route('public.contact') }}"><x-heroicon-o-phone class="size-4" aria-hidden="true" />Liên hệ</a>
            </nav>
            <div class="hidden items-center gap-3 md:flex">
                <x-ui.button :href="route('public.cart')" variant="ghost" size="sm" icon="shopping-cart">Giỏ hàng</x-ui.button>
                @auth
                    <x-ui.button :href="route('account')" variant="secondary" size="sm" icon="user-circle">Tài khoản</x-ui.button>
                @else
                    <x-ui.button :href="route('login')" variant="ghost" size="sm" icon="arrow-right-end-on-rectangle">Đăng nhập</x-ui.button>
                    <x-ui.button :href="route('register')" size="sm" icon="user-plus">Đăng ký</x-ui.button>
                @endauth
            </div>
            <details class="relative md:hidden">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-control border border-line px-3 py-2 text-sm font-semibold"><x-heroicon-o-bars-3 class="size-5" aria-hidden="true" />Menu</summary>
                <nav class="absolute right-0 z-20 mt-2 grid min-w-52 gap-1 rounded-panel border border-line bg-surface p-3 shadow-panel" aria-label="Điều hướng di động">
                    <a class="flex items-center gap-3 rounded-control px-3 py-2 hover:bg-surface-muted" href="{{ route('public.search') }}"><x-heroicon-o-magnifying-glass class="size-5" aria-hidden="true" />Sản phẩm</a>
                    <a class="flex items-center gap-3 rounded-control px-3 py-2 hover:bg-surface-muted" href="{{ route('public.projects') }}"><x-heroicon-o-building-office-2 class="size-5" aria-hidden="true" />Dự án</a>
                    <a class="flex items-center gap-3 rounded-control px-3 py-2 hover:bg-surface-muted" href="{{ route('public.about') }}"><x-heroicon-o-information-circle class="size-5" aria-hidden="true" />Giới thiệu</a>
                    <a class="flex items-center gap-3 rounded-control px-3 py-2 hover:bg-surface-muted" href="{{ route('public.contact') }}"><x-heroicon-o-phone class="size-5" aria-hidden="true" />Liên hệ</a>
                    <a class="flex items-center gap-3 rounded-control px-3 py-2 hover:bg-surface-muted" href="{{ route('public.cart') }}"><x-heroicon-o-shopping-cart class="size-5" aria-hidden="true" />Giỏ hàng</a>
                    <a class="flex items-center gap-3 rounded-control px-3 py-2 text-brand hover:bg-brand-soft" href="{{ auth()->check() ? route('account') : route('login') }}"><x-heroicon-o-user-circle class="size-5" aria-hidden="true" />{{ auth()->check() ? 'Tài khoản' : 'Đăng nhập' }}</a>
                </nav>
            </details>
        </div>
    </header>

    <main id="main-content">@yield('content')</main>

    <footer class="mt-20 border-t border-line bg-surface">
        <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 text-sm text-ink-muted sm:grid-cols-2 lg:px-8">
            <div>
                <img src="{{ asset('images/design/logo/logo-kaiyo-wat-1024x560.webp') }}" alt="Kaiyo" width="1024" height="560" loading="lazy" class="h-16 w-auto object-contain">
                <p class="mt-3 max-w-md">Nền tảng thương mại và báo giá phục vụ khách hàng cá nhân và doanh nghiệp.</p>
            </div>
            <nav class="flex flex-wrap gap-x-6 gap-y-3 sm:justify-end" aria-label="Điều hướng chân trang">
                <a class="hover:text-brand" href="{{ route('public.search') }}">Sản phẩm</a>
                <a class="hover:text-brand" href="{{ route('public.projects') }}">Dự án</a>
                <a class="hover:text-brand" href="{{ route('public.about') }}">Giới thiệu</a>
                <a class="hover:text-brand" href="{{ route('public.contact') }}">Liên hệ</a>
            </nav>
        </div>
    </footer>
</body>
</html>
