@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'icon' => null,
    'iconPosition' => 'start',
    'iconStyle' => 'outline',
])

@php
    $variantClasses = match ($variant) {
        'secondary' => 'border border-line bg-surface text-ink hover:bg-surface-muted',
        'danger' => 'bg-danger text-on-danger-strong hover:opacity-90',
        'ghost' => 'bg-transparent text-brand hover:bg-brand-soft hover:text-on-brand-soft',
        default => 'bg-brand text-on-brand hover:bg-brand-hover',
    };
    $sizeClasses = match ($size) {
        'sm' => 'min-h-9 px-3 py-1.5 text-sm',
        'lg' => 'min-h-12 px-5 py-3 text-base',
        default => 'min-h-11 px-4 py-2 text-sm',
    };
    $classes = "inline-flex items-center justify-center gap-2 rounded-control font-semibold transition $variantClasses $sizeClasses disabled:cursor-not-allowed disabled:opacity-50";
    $iconPrefix = match ($iconStyle) {
        'solid' => 's',
        'mini' => 'm',
        'micro' => 'c',
        default => 'o',
    };
    $iconComponent = $icon ? "heroicon-$iconPrefix-$icon" : null;
    $iconClasses = $size === 'sm' ? 'size-4 shrink-0' : 'size-5 shrink-0';
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon && $iconPosition === 'start')<x-dynamic-component :component="$iconComponent" :class="$iconClasses" aria-hidden="true" />@endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'end')<x-dynamic-component :component="$iconComponent" :class="$iconClasses" aria-hidden="true" />@endif
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>
        @if ($icon && $iconPosition === 'start')<x-dynamic-component :component="$iconComponent" :class="$iconClasses" aria-hidden="true" />@endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'end')<x-dynamic-component :component="$iconComponent" :class="$iconClasses" aria-hidden="true" />@endif
    </button>
@endif
