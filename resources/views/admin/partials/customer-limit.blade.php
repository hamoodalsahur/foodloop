{{-- One customer's no-show state as text: "Limited · 5 days" or "2 strikes". Pass `customer`. --}}
@if ($customer->isBlocked())
    @php($days = max(1, (int) ceil(now()->diffInHours($customer->blocked_until) / 24)))
    <strong class="text-danger">Limited · {{ $days }} {{ Str::plural('day', $days) }}</strong>
@else
    <span class="text-muted">{{ $customer->no_show_strikes }} {{ Str::plural('strike', $customer->no_show_strikes) }}</span>
@endif
