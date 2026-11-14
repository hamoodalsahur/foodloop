{{--
    The decision buttons for one shop, by its status. Pass `seller` and `admin` (the AdminService).
    Approve is only offered when the shop can be approved; otherwise the reason is shown.
--}}
@php
    use App\Enums\SellerStatus;

    $blocker = $admin->approvalBlocker($seller);
    $post = fn (string $action) => route("admin.sellers.{$action}", $seller);
@endphp

<div class="flex flex-wrap items-center gap-2 md:justify-end">
    @if ($seller->status !== SellerStatus::Approved)
        @if ($blocker)
            <p class="max-w-56 text-xs text-muted md:text-right">{{ $blocker }}</p>
        @else
            <form method="POST" action="{{ $post('approve') }}">
                @csrf
                <x-button type="submit">{{ $seller->status === SellerStatus::Pending ? 'Approve' : 'Approve again' }}<span class="sr-only"> {{ $seller->business_name }}</span></x-button>
            </form>
        @endif
    @endif

    @if ($seller->status === SellerStatus::Pending)
        <form method="POST" action="{{ $post('reject') }}">
            @csrf
            <x-button type="submit" variant="outline" class="border-danger text-danger">Reject<span class="sr-only"> {{ $seller->business_name }}</span></x-button>
        </form>
    @endif

    @if ($seller->status === SellerStatus::Approved)
        <form method="POST" action="{{ $post('suspend') }}">
            @csrf
            <x-button type="submit" variant="outline">Suspend<span class="sr-only"> {{ $seller->business_name }}</span></x-button>
        </form>
    @endif
</div>
