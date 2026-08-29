@extends('layouts.admin')
@section('title', 'Sản phẩm & Danh mục — Kaiyo Admin')

@section('content')
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-[0.16em] text-brand">Catalog</p>
    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-3xl font-bold">Sản phẩm & Danh mục</h1><p class="mt-2 max-w-3xl text-ink-muted">Quản lý dữ liệu công bố, biến thể, nội dung SEO, thông số và media. Sản phẩm mới luôn bắt đầu ở trạng thái nháp.</p></div>
        <x-ui.button :href="route('public.search')" variant="secondary" icon="arrow-top-right-on-square">Xem website</x-ui.button>
    </div>
    @if(session('status'))<x-ui.alert class="mt-6" tone="success">{{ session('status') }}</x-ui.alert>@endif
    @if($errors->any())<x-ui.alert class="mt-6" tone="danger" title="Không thể lưu dữ liệu">{{ $errors->first() }}</x-ui.alert>@endif

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <x-ui.card title="Tạo danh mục">
            <form method="POST" action="{{ route('admin.catalog.categories.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-ui.input name="name" label="Tên danh mục" required />
                <x-ui.input name="slug" label="Slug (để trống sẽ tự tạo)" />
                <label class="grid gap-1 text-sm font-medium">Danh mục cha
                    <select name="parent_public_id" class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="">— Danh mục gốc —</option>@foreach($categories as $category)<option value="{{ $category->public_id }}">{{ $category->name }}</option>@endforeach</select>
                </label>
                <x-ui.input name="sort_order" label="Thứ tự" type="number" value="0" required />
                <x-ui.button type="submit" class="sm:col-span-2" icon="folder-plus">Tạo danh mục</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Tạo sản phẩm nháp">
            <form method="POST" action="{{ route('admin.catalog.products.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-ui.input name="name" label="Tên sản phẩm" required />
                <x-ui.input name="slug" label="Slug (để trống sẽ tự tạo)" />
                <label class="grid gap-1 text-sm font-medium">Danh mục <select name="category_public_id" required class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="">Chọn danh mục</option>@foreach($categories->where('status','active') as $category)<option value="{{ $category->public_id }}">{{ $category->name }}</option>@endforeach</select></label>
                <label class="grid gap-1 text-sm font-medium">Thương hiệu <select name="brand_public_id" class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="">— Không chọn —</option>@foreach($brands as $brand)<option value="{{ $brand->public_id }}">{{ $brand->name }}</option>@endforeach</select></label>
                <x-ui.input name="variant_name" label="Tên biến thể đầu tiên" value="Mặc định" required />
                <x-ui.input name="variant_sku" label="SKU đầu tiên" required />
                <x-ui.input name="quantity_scale" label="Số chữ số thập phân số lượng" type="number" value="0" required />
                <x-ui.input name="seo_title" label="SEO title (tối đa 70 ký tự)" />
                <label class="grid gap-1 text-sm font-medium sm:col-span-2">Mô tả ngắn<textarea name="description" rows="3" maxlength="2000" class="rounded-control border border-line bg-surface px-3 py-2"></textarea></label>
                <label class="grid gap-1 text-sm font-medium sm:col-span-2">Nội dung chi tiết<textarea name="detailed_description" rows="5" maxlength="50000" class="rounded-control border border-line bg-surface px-3 py-2"></textarea></label>
                <label class="grid gap-1 text-sm font-medium sm:col-span-2">SEO description<textarea name="seo_description" rows="2" maxlength="180" class="rounded-control border border-line bg-surface px-3 py-2"></textarea></label>
                <x-ui.button type="submit" class="sm:col-span-2" icon="cube-transparent">Tạo sản phẩm</x-ui.button>
            </form>
        </x-ui.card>
    </div>

    <section class="mt-10" aria-labelledby="categories-title">
        <h2 id="categories-title" class="text-2xl font-bold">Danh mục hiện có</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($categories as $category)
                <form method="POST" action="{{ route('admin.catalog.categories.update', $category->public_id) }}" class="grid gap-3 rounded-panel border border-line bg-surface p-4">
                    @csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $category->lock_version }}">
                    <x-ui.input name="name" label="Tên" :value="$category->name" required />
                    <x-ui.input name="slug" label="Slug" :value="$category->slug" required />
                    <label class="grid gap-1 text-sm font-medium">Danh mục cha<select name="parent_public_id" class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="">— Danh mục gốc —</option>@foreach($categories->where('id','!=',$category->id) as $parent)<option value="{{ $parent->public_id }}" @selected($category->parent_id === $parent->id)>{{ $parent->name }}</option>@endforeach</select></label>
                    <label class="grid gap-1 text-sm font-medium">Trạng thái<select name="status" class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="active" @selected($category->status==='active')>Hoạt động</option><option value="inactive" @selected($category->status==='inactive')>Tạm ẩn</option></select></label>
                    <x-ui.button type="submit" variant="secondary" size="sm" icon="check">Lưu danh mục</x-ui.button>
                </form>
            @empty
                <x-ui.empty-state title="Chưa có danh mục" description="Tạo danh mục đầu tiên để bắt đầu Catalog." />
            @endforelse
        </div>
    </section>

    <section class="mt-12" aria-labelledby="products-title">
        <div class="flex items-center justify-between gap-4"><h2 id="products-title" class="text-2xl font-bold">Chi tiết sản phẩm</h2><span class="text-sm text-ink-muted">{{ $products->count() }} sản phẩm gần nhất</span></div>
        <div class="mt-5 space-y-5">
            @forelse($products as $product)
                <details class="group overflow-hidden rounded-panel border border-line bg-surface shadow-sm" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 p-5 hover:bg-surface-muted">
                        <span><span class="font-bold">{{ $product->name }}</span><span class="ml-2 rounded-full bg-surface-muted px-2 py-1 text-xs uppercase">{{ $product->status }}</span><span class="mt-1 block text-sm text-ink-muted">{{ $product->category->name }} · {{ $product->variants->count() }} biến thể</span></span>
                        <span class="flex items-center gap-3"><a class="text-sm font-semibold text-brand hover:underline" href="{{ route('public.product', $product->slug) }}" target="_blank">Xem trang</a><x-heroicon-o-chevron-down class="size-5 transition group-open:rotate-180" /></span>
                    </summary>

                    <div class="border-t border-line p-5 sm:p-6">
                        <form method="POST" action="{{ route('admin.catalog.products.update', $product->public_id) }}" class="grid gap-4 lg:grid-cols-2">
                            @csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $product->lock_version }}">
                            <x-ui.input name="name" label="Tên sản phẩm" :value="$product->name" required />
                            <x-ui.input name="slug" label="Slug" :value="$product->slug" required />
                            <label class="grid gap-1 text-sm font-medium">Danh mục<select name="category_public_id" required class="min-h-11 rounded-control border border-line bg-surface px-3">@foreach($categories->where('status','active') as $category)<option value="{{ $category->public_id }}" @selected($product->primary_category_id===$category->id)>{{ $category->name }}</option>@endforeach</select></label>
                            <label class="grid gap-1 text-sm font-medium">Thương hiệu<select name="brand_public_id" class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="">— Không chọn —</option>@foreach($brands as $brand)<option value="{{ $brand->public_id }}" @selected($product->brand_id===$brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
                            <label class="grid gap-1 text-sm font-medium">Trạng thái<select name="status" class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="draft" @selected($product->status==='draft')>Nháp</option><option value="active" @selected($product->status==='active')>Công khai</option><option value="inactive" @selected($product->status==='inactive')>Tạm ẩn</option></select></label>
                            <x-ui.input name="seo_title" label="SEO title" :value="$product->seo_title" />
                            <label class="grid gap-1 text-sm font-medium lg:col-span-2">Mô tả ngắn<textarea name="description" rows="3" maxlength="2000" class="rounded-control border border-line bg-surface px-3 py-2">{{ $product->description }}</textarea></label>
                            <label class="grid gap-1 text-sm font-medium lg:col-span-2">Nội dung chi tiết<textarea name="detailed_description" rows="7" maxlength="50000" class="rounded-control border border-line bg-surface px-3 py-2">{{ $product->detailed_description }}</textarea></label>
                            <label class="grid gap-1 text-sm font-medium lg:col-span-2">SEO description <span class="font-normal text-ink-muted">(tối đa 180 ký tự)</span><textarea name="seo_description" rows="2" maxlength="180" class="rounded-control border border-line bg-surface px-3 py-2">{{ $product->seo_description }}</textarea></label>
                            <x-ui.button type="submit" class="lg:col-span-2" icon="check">Lưu chi tiết sản phẩm</x-ui.button>
                        </form>

                        <div class="mt-8 grid gap-6 xl:grid-cols-3">
                            <section class="rounded-panel border border-line p-4" aria-labelledby="variants-{{ $product->id }}">
                                <h3 id="variants-{{ $product->id }}" class="font-bold">Biến thể</h3>
                                <div class="mt-3 space-y-3">
                                    @foreach($product->variants as $variant)
                                        <form method="POST" action="{{ route('admin.catalog.variants.update', $variant->public_id) }}" class="grid gap-2 rounded-xl bg-surface-muted p-3">
                                            @csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $variant->lock_version }}">
                                            <x-ui.input name="name" label="Tên biến thể" :value="$variant->name" required /><x-ui.input name="sku" label="SKU" :value="$variant->sku" required />
                                            <div class="grid grid-cols-2 gap-2"><x-ui.input name="quantity_scale" label="Thập phân" type="number" :value="$variant->quantity_scale" required /><label class="grid gap-1 text-sm font-medium">Trạng thái<select name="status" class="min-h-11 rounded-control border border-line bg-surface px-2"><option value="active" @selected($variant->status==='active')>Hoạt động</option><option value="inactive" @selected($variant->status==='inactive')>Tạm ẩn</option></select></label></div>
                                            <x-ui.button type="submit" variant="secondary" size="sm">Lưu biến thể</x-ui.button>
                                        </form>
                                    @endforeach
                                </div>
                                <form method="POST" action="{{ route('admin.catalog.variants.store', $product->public_id) }}" class="mt-4 grid gap-2 border-t border-line pt-4">@csrf<x-ui.input name="name" label="Biến thể mới" required /><x-ui.input name="sku" label="SKU mới" required /><x-ui.input name="quantity_scale" label="Thập phân" type="number" value="0" required /><x-ui.button type="submit" size="sm" icon="plus">Thêm biến thể</x-ui.button></form>
                            </section>

                            <section class="rounded-panel border border-line p-4" aria-labelledby="specs-{{ $product->id }}">
                                <h3 id="specs-{{ $product->id }}" class="font-bold">Thông số kỹ thuật</h3>
                                <dl class="mt-3 divide-y divide-line text-sm">
                                    @forelse($specifications[$product->id] ?? [] as $spec)<div class="grid grid-cols-[7rem_1fr] gap-2 py-2"><dt class="text-ink-muted">{{ $spec->name }}</dt><dd class="font-semibold">{{ $spec->text_value ?? $spec->integer_value ?? $spec->decimal_value ?? ($spec->boolean_value ? 'Có' : 'Không') }}</dd></div>@empty<p class="text-sm text-ink-muted">Chưa có thông số động.</p>@endforelse
                                </dl>
                                <form method="POST" action="{{ route('admin.catalog.specifications.store', $product->public_id) }}" class="mt-4 grid gap-2 border-t border-line pt-4">@csrf<x-ui.input name="label" label="Tên thông số" required /><x-ui.input name="value" label="Giá trị" required /><x-ui.button type="submit" size="sm" icon="plus">Lưu thông số</x-ui.button></form>
                            </section>

                            <section class="rounded-panel border border-line p-4" aria-labelledby="media-{{ $product->id }}">
                                <h3 id="media-{{ $product->id }}" class="font-bold">Ảnh, video & tài liệu</h3>
                                <ul class="mt-3 space-y-2 text-sm">
                                    @forelse($media[$product->id] ?? [] as $asset)
                                        <li class="flex items-center justify-between gap-2 rounded-xl bg-surface-muted p-3"><span class="min-w-0"><span class="block truncate font-semibold">{{ $asset->original_name }}</span><span class="text-xs text-ink-muted">{{ $asset->purpose }} · {{ number_format($asset->byte_size / 1024, 0) }} KB</span></span><form method="POST" action="{{ route('admin.catalog.media.destroy', [$product->public_id, $asset->public_id, $asset->purpose]) }}">@csrf @method('DELETE')<button class="text-danger hover:underline" type="submit">Gỡ</button></form></li>
                                    @empty
                                        <li class="text-ink-muted">Chưa có media được quản trị.</li>
                                    @endforelse
                                </ul>
                                <form method="POST" action="{{ route('admin.catalog.media.store', $product->public_id) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-line pt-4">@csrf
                                    <label class="grid gap-1 text-sm font-medium">Tệp<input type="file" name="file" required accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf" class="rounded-control border border-line bg-surface p-2 text-sm"></label>
                                    <label class="grid gap-1 text-sm font-medium">Mục đích<select name="purpose" class="min-h-11 rounded-control border border-line bg-surface px-3"><option value="primary">Ảnh đại diện</option><option value="gallery">Ảnh gallery</option><option value="video">Video</option><option value="document">Tài liệu PDF</option></select></label>
                                    <x-ui.input name="sort_order" label="Thứ tự" type="number" value="10" required />
                                    <x-ui.button type="submit" size="sm" icon="arrow-up-tray">Tải lên media</x-ui.button>
                                </form>
                            </section>
                        </div>
                    </div>
                </details>
            @empty
                <x-ui.empty-state title="Chưa có sản phẩm" description="Tạo sản phẩm nháp đầu tiên bằng biểu mẫu phía trên." />
            @endforelse
        </div>
    </section>
</section>
@endsection
