@props(['tone' => 'info', 'title' => null])

@php
    [$classes, $role] = match ($tone) {
        'success' => ['border-success bg-success-soft text-on-success', 'status'],
        'warning' => ['border-warning bg-warning-soft text-on-warning', 'alert'],
        'danger' => ['border-danger bg-danger-soft text-on-danger', 'alert'],
        default => ['border-info bg-info-soft text-on-info', 'status'],
    };
@endphp

<div role="{{ $role }}" {{ $attributes->class("rounded-control border-l-4 p-4 text-sm $classes") }}>
    @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
    <div @class(['mt-1' => $title])>{{ $slot }}</div>
</div>
