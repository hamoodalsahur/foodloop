{{--
    What the FoodLoop admins did about reports on this shop's listings in the last two weeks:
    a listing taken down, or a warning. Pass `messages` (from SellerOverview::adminMessages()).
--}}
@foreach ($messages as $message)
    <x-notice variant="danger" class="mb-6">
        <p class="font-bold">
            @if ($message->resolution === \App\Enums\ReportResolution::ListingHidden)
                FoodLoop took down “{{ $message->listing->title }}” after a customer report.
            @else
                Warning from FoodLoop about “{{ $message->listing->title }}”.
            @endif
        </p>
        <p class="mt-1">Customers reported: {{ $message->admin_note }}</p>
        <p class="mt-1 text-xs text-muted">
            {{ $message->resolved_at->format('j M, g:i A') }} ·
            @if ($message->resolution === \App\Enums\ReportResolution::ListingHidden)
                Waiting reservations were cancelled and refunded. The listing can’t be put back.
            @else
                Please check the listing. Repeated reports can get it taken down.
            @endif
        </p>
    </x-notice>
@endforeach
