<div class="mt-6 space-y-3">
@forelse($items as $item)
    <article class="rounded-control border border-line p-3 text-sm">
        <div class="flex flex-wrap justify-between gap-3"><div><strong>{{ $item['label'] }}</strong><p class="font-mono text-xs text-ink-muted">{{ $item['content_key'] }} · rev {{ $item['revision_no'] }} · v{{ $item['lock_version'] }}</p></div><span>{{ $item['has_published_revision'] ? 'LIVE' : strtoupper($item['status']) }}</span></div>
        @if($adminNavigation['content'] ?? false)
            <details class="mt-3"><summary class="cursor-pointer font-semibold text-brand">Tạo revision thay thế</summary>
                <form method="POST" action="{{ route($reviseRoute, $item['public_id']) }}" class="mt-3 grid gap-2">@csrf<input type="hidden" name="lock_version" value="{{ $item['lock_version'] }}">
                    @if($type === 'articles')<x-ui.input name="title" label="Tiêu đề mới" required /><x-ui.input name="excerpt" label="Tóm tắt mới" /><textarea name="body_markdown" aria-label="Nội dung Article mới" required class="rounded-control border border-line p-2"></textarea>@endif
                    @if($type === 'faqs')<x-ui.input name="question" label="Câu hỏi mới" required /><x-ui.input name="position" type="number" label="Thứ tự" value="0" required /><textarea name="answer_markdown" aria-label="Câu trả lời FAQ mới" required class="rounded-control border border-line p-2"></textarea>@endif
                    @if($type === 'banners')<x-ui.input name="headline" label="Headline mới" required /><x-ui.input name="body" label="Nội dung mới" /><x-ui.input name="cta_label" label="CTA label" /><x-ui.input name="cta_url" label="CTA URL" />@endif
                    @if($type === 'email-templates')<x-ui.input name="subject" label="Subject mới" required /><x-ui.input name="allowed_variables" label="Biến cho phép" /><textarea name="body_markdown" aria-label="Nội dung Email Template mới" required class="rounded-control border border-line p-2"></textarea>@endif
                    <x-ui.button type="submit" size="sm">Lưu revision</x-ui.button>
                </form>
            </details>
            @if($type !== 'email-templates')
                <details class="mt-3"><summary class="cursor-pointer font-semibold text-brand">Media của revision</summary>
                    <div class="mt-2 space-y-2">@foreach($item['media'] as $reference)<div class="flex items-center justify-between gap-2"><span>{{ $reference['original_name'] }} · {{ $reference['purpose'] }}</span><form method="POST" action="{{ route('admin.content.media.detach', ['type'=>$type,'content'=>$item['public_id'],'asset'=>$reference['public_id'],'purpose'=>$reference['purpose']]) }}">@csrf @method('DELETE')<input type="hidden" name="lock_version" value="{{ $item['lock_version'] }}"><x-ui.button type="submit" size="sm" variant="secondary">Gỡ</x-ui.button></form></div>@endforeach</div>
                    <form method="POST" action="{{ route('admin.content.media.attach', ['type'=>$type,'content'=>$item['public_id']]) }}" class="mt-3 grid gap-2">@csrf<input type="hidden" name="lock_version" value="{{ $item['lock_version'] }}"><x-ui.input name="asset_public_id" label="Media public ID" required /><x-ui.input name="purpose" label="Purpose" required /><x-ui.input name="sort_order" type="number" label="Thứ tự" value="0" required /><x-ui.button type="submit" size="sm">Gắn media</x-ui.button></form>
                </details>
            @endif
        @endif
        @if($adminNavigation['content_publish'] ?? false)<div class="mt-3 flex gap-2">
            @if(!$item['has_published_revision'])<form method="POST" action="{{ route($publishRoute,$item['public_id']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $item['lock_version'] }}"><x-ui.button type="submit" size="sm">Xuất bản</x-ui.button></form>@endif
            @if($item['has_published_revision'])<form method="POST" action="{{ route('admin.content.unpublish', ['type'=>$type,'content'=>$item['public_id']]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $item['lock_version'] }}"><x-ui.button type="submit" size="sm" variant="secondary">Gỡ xuất bản</x-ui.button></form>@endif
        </div>@endif
        @if(($adminNavigation['content_publish'] ?? false) && $type !== 'email-templates')
            <details class="mt-3"><summary class="cursor-pointer font-semibold text-brand">Lên lịch</summary>
                <form method="POST" action="{{ route('admin.content.schedule', ['type'=>$type,'content'=>$item['public_id']]) }}" class="mt-3 grid gap-2">@csrf<input type="hidden" name="lock_version" value="{{ $item['lock_version'] }}"><label>Hành động <select name="action" class="rounded-control border border-line p-2"><option value="publish">Xuất bản</option><option value="unpublish">Gỡ xuất bản</option></select></label><x-ui.input name="due_at" type="datetime-local" label="Thời điểm" required /><x-ui.input name="operation_key" label="Operation key duy nhất" required /><x-ui.button type="submit" size="sm">Lưu lịch</x-ui.button></form>
            </details>
        @endif
    </article>
@empty<p class="text-sm text-ink-muted">Chưa có nội dung.</p>@endforelse
</div>
