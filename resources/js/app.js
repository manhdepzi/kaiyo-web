import './bootstrap';

document.querySelectorAll('[data-slideshow]').forEach((slideshow) => {
    const slides = [...slideshow.querySelectorAll('[data-slide]')];
    const dots = [...slideshow.querySelectorAll('[data-slide-dot]')];
    const interval = Number(slideshow.dataset.interval || 3000);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let active = 0;
    let timer = null;

    const show = (index) => {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            const isActive = slideIndex === active;
            slide.hidden = !isActive;
            slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
        dots.forEach((dot, dotIndex) => {
            const isActive = dotIndex === active;
            dot.setAttribute('aria-current', isActive ? 'true' : 'false');
            dot.classList.toggle('opacity-45', !isActive);
        });
    };

    const stop = () => {
        window.clearInterval(timer);
        timer = null;
    };
    const start = () => {
        stop();
        if (slides.length > 1 && !reducedMotion.matches) {
            timer = window.setInterval(() => show(active + 1), interval);
        }
    };

    slideshow.querySelector('[data-slide-prev]')?.addEventListener('click', () => { show(active - 1); start(); });
    slideshow.querySelector('[data-slide-next]')?.addEventListener('click', () => { show(active + 1); start(); });
    dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.slideDot)); start(); }));
    slideshow.addEventListener('mouseenter', stop);
    slideshow.addEventListener('mouseleave', start);
    slideshow.addEventListener('focusin', stop);
    slideshow.addEventListener('focusout', start);
    reducedMotion.addEventListener('change', start);
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
    start();
});

document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
    const slides = [...gallery.querySelectorAll('[data-gallery-slide]')];
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
    const counter = gallery.querySelector('[data-gallery-counter]');
    const zoom = gallery.querySelector('[data-deep-zoom]');
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    let active = 0;

    const show = (index) => {
        if (slides.length === 0) return;
        zoom?.classList.remove('is-zooming');
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            const selected = slideIndex === active;
            slide.hidden = !selected;
            slide.setAttribute('aria-hidden', selected ? 'false' : 'true');
            if (selected) {
                slide.classList.remove('product-gallery-slide-enter');
                void slide.offsetWidth;
                slide.classList.add('product-gallery-slide-enter');
            }
            if (!selected) slide.querySelector('video')?.pause();
        });
        thumbs.forEach((thumb, thumbIndex) => thumb.setAttribute('aria-current', thumbIndex === active ? 'true' : 'false'));
        if (counter) counter.textContent = String(active + 1);
    };

    thumbs.forEach((thumb) => thumb.addEventListener('click', () => show(Number(thumb.dataset.galleryThumb))));
    gallery.querySelector('[data-gallery-prev]')?.addEventListener('click', () => show(active - 1));
    gallery.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(active + 1));

    zoom?.addEventListener('pointermove', (event) => {
        if (!finePointer.matches) return;
        const image = slides[active]?.querySelector('[data-zoom-image]');
        if (!image) return;
        const bounds = zoom.getBoundingClientRect();
        const x = Math.max(0, Math.min(100, ((event.clientX - bounds.left) / bounds.width) * 100));
        const y = Math.max(0, Math.min(100, ((event.clientY - bounds.top) / bounds.height) * 100));
        image.style.transformOrigin = `${x}% ${y}%`;
        zoom.classList.add('is-zooming');
    });
    zoom?.addEventListener('pointerleave', () => {
        zoom.classList.remove('is-zooming');
        const image = slides[active]?.querySelector('[data-zoom-image]');
        if (image) image.style.transformOrigin = 'center';
    });

    show(0);
});

document.querySelectorAll('[data-product-order-form]').forEach((form) => {
    const variant = form.querySelector('[data-selected-variant-input]');
    const quote = form.querySelector('[data-variant-quote]');
    if (!variant || !quote) return;

    const variantOptions = [...document.querySelectorAll('[name="product_variant_preview"]')];
    const selectedName = document.querySelector('[data-selected-variant]');
    const selectedSku = document.querySelector('[data-selected-sku]');
    const quantity = form.querySelector('[name="quantity"]');

    const updateVariant = (option) => {
        variant.value = option.value;
        if (selectedName) selectedName.textContent = option.dataset.name || '';
        if (selectedSku) selectedSku.textContent = option.dataset.sku ? `SKU: ${option.dataset.sku}` : '';
        const url = new URL(quote.dataset.baseUrl, window.location.origin);
        url.searchParams.set('variant', option.value);
        quote.href = url.toString();
    };

    variantOptions.forEach((option) => option.addEventListener('change', () => updateVariant(option)));
    const initiallySelected = variantOptions.find((option) => option.checked) || variantOptions[0];
    if (initiallySelected) updateVariant(initiallySelected);

    const stepQuantity = (amount) => {
        if (!quantity) return;
        const minimum = Number(quantity.min || 1);
        const current = Number.parseInt(quantity.value, 10);
        quantity.value = String(Math.max(minimum, (Number.isFinite(current) ? current : minimum) + amount));
        quantity.dispatchEvent(new Event('change', { bubbles: true }));
    };

    form.querySelector('[data-quantity-decrease]')?.addEventListener('click', () => stepQuantity(-1));
    form.querySelector('[data-quantity-increase]')?.addEventListener('click', () => stepQuantity(1));
});
