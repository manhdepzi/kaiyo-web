@props(['title', 'description' => null])

<section {{ $attributes->class('rounded-panel border border-dashed border-line bg-surface p-8 text-center') }}>
    <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
    @if ($description)<p class="mx-auto mt-2 max-w-prose text-sm text-ink-muted">{{ $description }}</p>@endif
    @if (trim((string) $slot) !== '')<div class="mt-5">{{ $slot }}</div>@endif
</section>
