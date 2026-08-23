@props(['label' => 'Đang tải'])

<div role="status" {{ $attributes->class('animate-pulse rounded-control bg-surface-muted') }}>
    <span class="sr-only">{{ $label }}</span>
</div>
