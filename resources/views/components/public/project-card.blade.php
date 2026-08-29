@props(['project', 'priority' => false])

<article id="{{ $project->slug }}" data-project-card {{ $attributes->class('group overflow-hidden rounded-panel border border-line bg-surface shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-panel') }}>
    <div class="aspect-[4/3] overflow-hidden bg-surface-muted">
        <img src="{{ asset(ltrim($project->imagePath, '/')) }}" alt="{{ $project->title }}" width="960" height="720" @if($priority) fetchpriority="high" @else loading="lazy" @endif class="h-full w-full object-cover transition duration-700 ease-out group-hover:scale-105">
    </div>
    <div class="p-5 sm:p-6">
        @if ($project->service)
            <p class="text-xs font-semibold uppercase tracking-widest text-brand">{{ $project->service }}</p>
        @endif
        <h3 class="mt-2 text-lg font-bold leading-snug text-ink">{{ $project->title }}</h3>
        @if ($project->location || $project->completionYear)
            <dl class="mt-4 grid gap-2 text-sm text-ink-muted">
                @if ($project->location)
                    <div class="flex gap-2"><dt class="sr-only">Địa điểm</dt><x-heroicon-o-map-pin class="mt-0.5 size-4 shrink-0 text-brand" aria-hidden="true" /><dd>{{ $project->location }}</dd></div>
                @endif
                @if ($project->completionYear)
                    <div class="flex gap-2"><dt class="sr-only">Năm hoàn thành</dt><x-heroicon-o-calendar-days class="mt-0.5 size-4 shrink-0 text-brand" aria-hidden="true" /><dd>Hoàn thành năm {{ $project->completionYear }}</dd></div>
                @endif
            </dl>
        @endif
    </div>
</article>
