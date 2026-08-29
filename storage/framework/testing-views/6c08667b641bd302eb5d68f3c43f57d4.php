<?php $__env->startSection('title', 'Giới thiệu — Kaiyo'); ?>
<?php $__env->startSection('meta_description', 'Tìm hiểu cách Kaiyo tổ chức trải nghiệm thương mại B2C và B2B trên một nền tảng thống nhất.'); ?>
<?php $__env->startSection('content'); ?>
<article class="mx-auto max-w-4xl px-5 py-16 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Về Kaiyo</p>
    <h1 class="mt-4 text-4xl font-bold tracking-tight">Một nền tảng, hai hành trình mua hàng</h1>
    <p class="mt-6 text-lg leading-8 text-ink-muted">Kaiyo phục vụ cả giao dịch thương mại điện tử và quy trình báo giá doanh nghiệp. Giá, tồn kho, phê duyệt, đơn hàng, thanh toán và giao nhận được nối bằng các trạng thái có thể kiểm tra.</p>
    <div class="mt-12 grid gap-5 sm:grid-cols-2">
        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => 'Khách hàng cá nhân','description' => 'Tra cứu, giỏ hàng và checkout với giá cùng tồn kho được xác nhận tại thời điểm cam kết.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Khách hàng cá nhân','description' => 'Tra cứu, giỏ hàng và checkout với giá cùng tồn kho được xác nhận tại thời điểm cam kết.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['title' => 'Khách hàng doanh nghiệp','description' => 'Báo giá có phiên bản, quyền phê duyệt rõ ràng và chuyển đổi thành đúng một đơn hàng.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Khách hàng doanh nghiệp','description' => 'Báo giá có phiên bản, quyền phê duyệt rõ ràng và chuyển đổi thành đúng một đơn hàng.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
    </div>
</article>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.public', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\kaiyow\resources\views/public/about.blade.php ENDPATH**/ ?>