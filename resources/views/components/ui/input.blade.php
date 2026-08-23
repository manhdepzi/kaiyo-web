@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $controlId = $attributes->get('id', 'field-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $name));
    $errorId = $controlId.'-error';
    $helpId = $controlId.'-help';
    $hasError = $errors->has($name);
    $describedBy = collect([$help ? $helpId : null, $hasError ? $errorId : null])->filter()->implode(' ');
@endphp

<div>
    <label for="{{ $controlId }}" class="block text-sm font-medium text-ink">
        {{ $label }}
        @if ($required)<span aria-hidden="true" class="text-danger">*</span>@endif
    </label>
    <input
        id="{{ $controlId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @required($required)
        @disabled($disabled)
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except(['id'])->class([
            'mt-2 min-h-11 w-full rounded-control border bg-surface px-3 py-2 text-ink shadow-sm transition placeholder:text-ink-muted disabled:cursor-not-allowed disabled:bg-surface-muted disabled:opacity-70',
            'border-danger' => $hasError,
            'border-line hover:border-ink-muted' => ! $hasError,
        ]) }}
    >
    @if ($help)
        <p id="{{ $helpId }}" class="mt-1.5 text-sm text-ink-muted">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $errorId }}" class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p>
    @enderror
</div>
