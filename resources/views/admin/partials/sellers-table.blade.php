{{--
    A list of shops with what an admin checks and the decision buttons.
    Pass `sellers` and `admin` (the AdminService). A table on wide screens, cards on phones.
--}}
@php
    use App\Support\Distance;
    use App\Support\PickupWindow;

    $sent = fn ($seller) => match (true) {
        $seller->created_at->isToday() => $seller->created_at->diffForHumans(short: true),
        default => PickupWindow::day($seller->created_at),
    };
@endphp

<div class="hidden md:block">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b-2 border-forest text-[0.6875rem] uppercase tracking-wider text-muted">
                <th class="px-2 py-2 font-bold">Business</th>
                <th class="px-2 py-2 font-bold">SSM no.</th>
                <th class="px-2 py-2 font-bold">Halal claim</th>
                <th class="px-2 py-2 font-bold">Sent</th>
                <th class="px-2 py-2 text-right font-bold">Decision</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sellers as $seller)
                <tr class="border-b border-line align-middle">
                    <td class="px-2 py-3">
                        <p class="font-bold">{{ $seller->business_name }}</p>
                        <p class="text-xs text-muted">{{ $seller->user->email }}@if (Distance::toSeller($seller) !== null) · {{ Distance::label(Distance::toSeller($seller)) }}@endif</p>
                    </td>
                    <td class="px-2 py-3 whitespace-nowrap">@include('admin.partials.seller-ssm', ['seller' => $seller])</td>
                    <td class="px-2 py-3">@include('admin.partials.seller-halal', ['seller' => $seller])</td>
                    <td class="px-2 py-3 whitespace-nowrap text-muted">{{ $sent($seller) }}</td>
                    <td class="px-2 py-2">@include('admin.partials.seller-decision', ['seller' => $seller])</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<ul class="space-y-3 md:hidden">
    @foreach ($sellers as $seller)
        <li class="rounded-2xl border border-line p-3">
            <p class="font-bold">{{ $seller->business_name }}</p>
            <p class="text-xs text-muted">{{ $seller->user->email }} · sent {{ $sent($seller) }}</p>
            <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-muted">SSM no.</dt>
                <dd>@include('admin.partials.seller-ssm', ['seller' => $seller])</dd>
                <dt class="text-muted">Halal claim</dt>
                <dd>@include('admin.partials.seller-halal', ['seller' => $seller])</dd>
            </dl>
            <div class="mt-3">@include('admin.partials.seller-decision', ['seller' => $seller])</div>
        </li>
    @endforeach
</ul>
