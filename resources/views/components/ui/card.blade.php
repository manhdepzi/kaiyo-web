@props(['title' => null, 'description' => null])

<section {{ $attributes->class('rounded-panel border border-line bg-surface p-6 shadow-panel') }}>
    @if ($title)
        <header class="mb-5">
            <h2 class="text-lg font-semibold text-ink">{{ $title }}</h2>
            @if ($description)<p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>@endif
        </header>
    @endif
    {{ $slot }}
</section>
