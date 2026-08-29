@props(['tone' => 'info', 'title' => null])

@php
    [$classes, $role, $icon] = match ($tone) {
        'success' => ['border-success bg-success-soft text-on-success', 'status', 'heroicon-s-check-circle'],
        'warning' => ['border-warning bg-warning-soft text-on-warning', 'alert', 'heroicon-s-exclamation-triangle'],
        'danger' => ['border-danger bg-danger-soft text-on-danger', 'alert', 'heroicon-s-x-circle'],
        default => ['border-info bg-info-soft text-on-info', 'status', 'heroicon-s-information-circle'],
    };
@endphp

<div role="{{ $role }}" {{ $attributes->class("rounded-control border-l-4 p-4 text-sm $classes") }}>
    <div class="flex items-start gap-3">
        <x-dynamic-component :component="$icon" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
        <div class="min-w-0">
            @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
            <div @class(['mt-1' => $title])>{{ $slot }}</div>
        </div>
    </div>
</div>
