{{-- Open reports grouped by listing, then what was decided before. Data from Admin\ReportController@index. --}}
<x-layouts.admin title="Reports" active="reports">
    <x-slot:subtitle>Each button deals with every open report on that listing.</x-slot:subtitle>

    @include('admin.partials.flash')

    <section>
        <h2 class="font-display text-2xl font-bold">Open</h2>
        <div class="mt-3 space-y-3">
            @forelse ($reported as $listing)
                @include('admin.partials.reported-listing', ['listing' => $listing])
            @empty
                <x-empty-state title="No open reports" message="When a customer reports a listing, it shows up here." />
            @endforelse
        </div>
    </section>

    @if ($resolved->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-display text-2xl font-bold">Dealt with</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($resolved as $report)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-white p-4">
                        <div class="min-w-0">
                            <p class="font-bold">{{ $report->listing->title }} · {{ $report->listing->seller->business_name }}</p>
                            <p class="text-sm text-muted">
                                {{ $report->reason->label() }}@if (filled($report->details)): “{{ $report->details }}”@endif
                            </p>
                            <p class="text-xs text-muted">
                                {{ $report->resolved_at?->format('j M, g:i A') }}@if ($report->resolver) · by {{ $report->resolver->name }}@endif
                            </p>
                        </div>
                        <x-tag :variant="$report->resolution ? 'forest' : 'outline'">{{ $report->resolution?->label() ?? $report->status->label() }}</x-tag>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.admin>
