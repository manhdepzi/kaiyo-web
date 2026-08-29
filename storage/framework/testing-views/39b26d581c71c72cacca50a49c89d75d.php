<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tone' => 'neutral']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['tone' => 'neutral']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $classes = match ($tone) {
        'success' => 'bg-success-soft text-on-success',
        'warning' => 'bg-warning-soft text-on-warning',
        'danger' => 'bg-danger-soft text-on-danger',
        'info' => 'bg-info-soft text-on-info',
        default => 'bg-surface-muted text-ink',
    };
?>

<span <?php echo e($attributes->class("inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold $classes")); ?>><?php echo e($slot); ?></span>
<?php /**PATH C:\laragon\www\kaiyow\resources\views/components/ui/badge.blade.php ENDPATH**/ ?>