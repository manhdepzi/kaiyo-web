@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'bg-success-soft text-on-success',
        'warning' => 'bg-warning-soft text-on-warning',
        'danger' => 'bg-danger-soft text-on-danger',
        'info' => 'bg-info-soft text-on-info',
        default => 'bg-surface-muted text-ink',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold $classes") }}>{{ $slot }}</span>
