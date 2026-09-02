@props(['title' => null, 'description' => null, 'icon' => null, 'iconPosition' => 'start'])

<section {{ $attributes->class('rounded-panel border border-line bg-surface p-6 shadow-panel') }}>
@if ($title)
    <header class="mb-5 flex flex-col items-start gap-2">
        @if($icon && $iconPosition === 'start')
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="size-5 text-brand" aria-hidden="true" />
        @endif
        <h2 class="text-lg font-semibold text-ink flex items-center">{{ $title }}</h2>
        @if($icon && $iconPosition === 'end')
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="size-5 text-brand ml-2" aria-hidden="true" />
        @endif
        @if ($description)
            <p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>
        @endif
    </header>
@endif
    {{ $slot }}
</section>
