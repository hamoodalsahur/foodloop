{{-- A listing's status as a small label. Pass `listing`. --}}
@php($status = $listing->status)
<x-tag :variant="match ($status) {
    \App\Enums\ListingStatus::Live => 'forest',
    \App\Enums\ListingStatus::SoldOut => 'marigold',
    default => 'outline',
}">{{ $status->label() }}</x-tag>
