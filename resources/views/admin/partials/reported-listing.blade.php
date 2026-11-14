{{--
    One reported listing with what customers wrote and the three decisions (mockup c10).
    Pass `listing` with its open reports loaded as `reports` and its `seller`.
--}}
@php($count = $listing->reports->count())

<article @class(['rounded-3xl border-2 bg-white p-4', 'border-forest' => $count > 1, 'border-line' => $count === 1])>
    <div class="flex items-start gap-3">
        <x-food-photo :path="$listing->photo_path" alt="" size="sm" />
        <div class="min-w-0 flex-1">
            <h3 class="font-display font-bold leading-snug">{{ $listing->title }} · {{ $listing->seller->business_name }}</h3>
            <p class="text-xs text-muted">{{ $listing->status->label() }} · {{ $listing->halal_status->label() }}</p>
        </div>
        <span @class(['shrink-0 rounded-full px-2.5 py-1 text-xs font-bold', 'bg-danger-soft text-danger' => $count > 1, 'bg-cream text-forest' => $count === 1])>
            {{ $count }} {{ Str::plural('report', $count) }}
        </span>
    </div>

    <ul class="mt-3 space-y-1 text-sm">
        @foreach ($listing->reports as $report)
            <li>
                <span class="font-semibold">{{ $report->reason->label() }}@if (filled($report->details)):@endif</span>
                @if (filled($report->details))
                    “{{ $report->details }}”
                @endif
            </li>
        @endforeach
    </ul>

    <div class="mt-3 flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.reports.hide', $listing) }}">
            @csrf
            <x-button type="submit">Hide listing<span class="sr-only">: {{ $listing->title }}</span></x-button>
        </form>
        <form method="POST" action="{{ route('admin.reports.warn', $listing) }}">
            @csrf
            <x-button type="submit" variant="outline">Warn seller<span class="sr-only"> about {{ $listing->title }}</span></x-button>
        </form>
        <form method="POST" action="{{ route('admin.reports.dismiss', $listing) }}">
            @csrf
            <x-button type="submit" variant="outline" class="border-line">Dismiss<span class="sr-only"> reports on {{ $listing->title }}</span></x-button>
        </form>
    </div>
</article>
