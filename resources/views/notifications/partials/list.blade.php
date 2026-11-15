{{--
    The messages in someone's inbox, newest first. Pass `messages` (a paginator of
    database notifications). The ones that are new on this visit get a marigold dot.
--}}
@if ($messages->isEmpty())
    <x-empty-state title="Nothing yet" :message="$emptyMessage" />
@else
    <ul class="space-y-3">
        @foreach ($messages as $message)
            @php
                $isNew = $message->unread();
                $url = $message->data['url'] ?? null;
            @endphp
            <li>
                <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif
                    @class([
                        'flex min-h-11 min-w-0 gap-3 rounded-3xl bg-white p-4',
                        'transition-shadow hover:shadow-md' => $url,
                        'border-2 border-forest' => $isNew,
                    ])>
                    <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-marigold' => $isNew, 'bg-line' => ! $isNew]) aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-display font-bold leading-snug">
                            @if ($isNew)<span class="sr-only">New: </span>@endif{{ $message->data['title'] ?? 'Message' }}
                        </span>
                        <span class="mt-1 block text-sm text-muted">{{ $message->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-xs text-muted">{{ $message->created_at->isToday() ? $message->created_at->diffForHumans() : $message->created_at->format('D j M, g:i A') }}</span>
                    </span>
                </{{ $url ? 'a' : 'div' }}>
            </li>
        @endforeach
    </ul>

    @if ($messages->hasPages())
        <nav aria-label="More notifications" class="mt-6 flex items-center justify-between gap-3">
            @if ($messages->onFirstPage())
                <span></span>
            @else
                <x-button variant="outline" :href="$messages->previousPageUrl()">Newer</x-button>
            @endif
            @if ($messages->hasMorePages())
                <x-button variant="outline" :href="$messages->nextPageUrl()">Older</x-button>
            @endif
        </nav>
    @endif
@endif
