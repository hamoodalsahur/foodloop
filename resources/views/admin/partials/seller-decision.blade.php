{{--
    The decision buttons for one shop, by its status. Pass `seller` and `admin` (the AdminService).
    Approve is only offered when the shop can be approved; otherwise the reason is shown.
    A waiting shop can also be asked for more info first ("Ask for info", mockup c10).
--}}
@php
    use App\Enums\SellerStatus;

    $blocker = $admin->approvalBlocker($seller);
    $post = fn (string $action) => route("admin.sellers.{$action}", $seller);
    // This partial is on the page twice (table and phone cards), so the field needs its own id each time.
    $askId = 'ask-'.$seller->id.'-'.Str::random(4);
@endphp

@if (filled($seller->info_request))
    <p class="mb-2 text-xs text-muted md:text-right">
        Asked {{ $seller->info_requested_at->diffForHumans() }}: “{{ $seller->info_request }}” · waiting for the shop
    </p>
@endif

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
        {{-- Opens in place; works without JavaScript. Once open it takes its own line. --}}
        <details class="group open:order-last open:w-full">
            <summary class="inline-flex min-h-11 cursor-pointer list-none items-center justify-center rounded-2xl border-2 border-forest bg-white px-5 py-2.5 text-sm font-semibold hover:bg-cream [&::-webkit-details-marker]:hidden">
                {{ filled($seller->info_request) ? 'Ask again' : 'Ask for info' }}<span class="sr-only">: {{ $seller->business_name }}</span>
            </summary>
            <form method="POST" action="{{ $post('ask') }}" class="mt-2 rounded-2xl bg-cream p-3 text-left">
                @csrf
                <label for="{{ $askId }}" class="block text-sm font-semibold">What do you need from {{ $seller->business_name }}?</label>
                <textarea id="{{ $askId }}" name="question" rows="2" required maxlength="300"
                          class="mt-1.5 block w-full rounded-xl border-2 border-forest bg-white px-3.5 py-3 text-base text-forest placeholder:text-muted"
                          placeholder="e.g. Please upload your JAKIM certificate."></textarea>
                <div class="mt-2 flex justify-end">
                    <x-button type="submit">Send question</x-button>
                </div>
            </form>
        </details>

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
