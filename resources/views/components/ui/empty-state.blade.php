@props(['title', 'description' => null, 'icon' => 'inbox'])

<section {{ $attributes->class('rounded-panel border border-dashed border-line bg-surface p-8 text-center') }}>
    <span class="mx-auto grid size-12 place-items-center rounded-full bg-brand-soft text-brand" aria-hidden="true">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" />
    </span>
    <h2 class="mt-4 text-base font-semibold text-ink">{{ $title }}</h2>
    @if ($description)<p class="mx-auto mt-2 max-w-prose text-sm text-ink-muted">{{ $description }}</p>@endif
    @if (trim((string) $slot) !== '')<div class="mt-5">{{ $slot }}</div>@endif
</section>
