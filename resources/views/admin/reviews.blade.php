@extends('layouts.admin')

@section('title', 'Kiểm duyệt đánh giá — Kaiyo')

@section('content')
<section class="mx-auto max-w-6xl px-5 py-10 lg:px-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-brand">Content governance</p>
    <h1 class="mt-2 text-3xl font-bold">Kiểm duyệt đánh giá sản phẩm</h1>
    <p class="mt-2 text-ink-muted">Chỉ nội dung được duyệt mới xuất hiện công khai và tham gia dữ liệu SEO.</p>
    @if(session('status'))<x-ui.alert class="mt-5" tone="success">{{ session('status') }}</x-ui.alert>@endif
    @error('review')<x-ui.alert class="mt-5" tone="danger">{{ $message }}</x-ui.alert>@enderror
    <div class="mt-7 space-y-4">
        @forelse($reviews as $review)
            <article class="rounded-panel border border-line bg-surface p-5">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm text-brand">{{ $review->product_name }} · {{ $review->customer_name }}</p><h2 class="mt-1 text-lg font-bold">{{ $review->title }}</h2></div><x-ui.badge :tone="$review->status === 'approved' ? 'success' : ($review->status === 'rejected' ? 'danger' : 'warning')">{{ $review->status }} · {{ $review->rating }}/5</x-ui.badge></div>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-ink-muted">{{ $review->body }}</p>
                @if($review->status === 'pending')
                    <form method="POST" action="{{ route('admin.reviews.moderate', $review->public_id) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                        @csrf @method('PATCH')<input type="hidden" name="expected_version" value="{{ $review->lock_version }}">
                        <x-ui.input :id="'review-'.$review->public_id.'-reason'" name="reason" label="Lý do kiểm duyệt" required />
                        <x-ui.button type="submit" name="decision" value="approve" icon="check">Duyệt</x-ui.button>
                        <x-ui.button type="submit" name="decision" value="reject" variant="danger" icon="x-mark">Từ chối</x-ui.button>
                    </form>
                @elseif($review->moderation_reason)<p class="mt-3 text-sm"><strong>Lý do:</strong> {{ $review->moderation_reason }}</p>@endif
            </article>
        @empty
            <x-ui.empty-state title="Chưa có đánh giá" description="Các đánh giá mua hàng đã xác minh sẽ xuất hiện tại đây." />
        @endforelse
    </div>
</section>
@endsection
