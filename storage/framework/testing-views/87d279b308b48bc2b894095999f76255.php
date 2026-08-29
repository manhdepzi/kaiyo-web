<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['hit']));

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

foreach (array_filter((['hit']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php $presentations = app('App\Modules\Catalog\Application\Support\ProductPresentationCatalog'); ?>
<?php ($image = $presentations->primaryFor($hit->slug, $hit->productName)); ?>

<article <?php echo e($attributes->class('group overflow-hidden rounded-panel border border-line bg-surface shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-panel')); ?>>
    <a href="<?php echo e(route('public.product', $hit->slug)); ?>" class="block aspect-[4/3] overflow-hidden bg-white" tabindex="-1" aria-hidden="true">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image): ?>
            <img src="<?php echo e($image->url); ?>" alt="" width="<?php echo e($image->width); ?>" height="<?php echo e($image->height); ?>" loading="lazy" class="h-full w-full object-contain p-5 transition duration-500 ease-out group-hover:scale-110">
        <?php else: ?>
            <span class="grid h-full place-items-center text-sm text-ink-muted">Ảnh đang cập nhật</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </a>
    <div class="p-5">
    <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($hit->sku); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
    <h2 class="mt-4 text-lg font-semibold"><a class="hover:text-brand hover:underline" href="<?php echo e(route('public.product', $hit->slug)); ?>"><?php echo e($hit->productName); ?></a></h2>
    <p class="mt-1 text-sm text-ink-muted"><?php echo e($hit->variantName); ?></p>
    <p class="mt-5 text-sm font-medium text-brand">Giá và tồn kho được xác nhận khi chọn mua hoặc yêu cầu báo giá.</p>
    </div>
</article>
<?php /**PATH C:\laragon\www\kaiyow\resources\views/components/public/product-hit.blade.php ENDPATH**/ ?>