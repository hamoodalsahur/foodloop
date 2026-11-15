{{--
    The inbox behind the bell. Same list for everyone; the frame around it is the
    customer, seller or admin layout. Customers also manage their alerts here.
    Data comes from App\Http\Controllers\NotificationController.
--}}
@if ($user->isSeller())
    <x-layouts.seller title="Notifications" active="notifications">
        <x-slot:subtitle>Reservations, pickups and messages from FoodLoop. Nothing is sent by email or SMS.</x-slot:subtitle>

        @include('notifications.partials.list', ['emptyMessage' => 'New reservations, cancellations and messages from FoodLoop show up here.'])
    </x-layouts.seller>
@elseif ($user->isAdmin())
    <x-layouts.admin title="Notifications" active="notifications">
        <x-slot:subtitle>New shops to approve and new reports.</x-slot:subtitle>

        @include('notifications.partials.list', ['emptyMessage' => 'New shops and new reports show up here.'])
    </x-layouts.admin>
@else
    <x-layouts.customer title="Notifications">
        <h1 class="font-display text-4xl font-bold tracking-tight">Notifications</h1>
        <p class="mt-1 text-sm text-muted">Shown here in the app only. FoodLoop doesn’t send email, push or SMS.</p>

        <div class="mt-6">
            @include('notifications.partials.list', ['emptyMessage' => 'Your bookings, pickups and deal alerts show up here.'])
        </div>

        {{-- Deal alerts: every new deal --}}
        <x-card as="section" class="mt-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-display text-xl font-bold">Deal alerts</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ $user->deal_alerts ? 'On. You get a notification for every new deal.' : 'Off. Turn on to get a notification for every new deal.' }}
                    </p>
                </div>
                <form method="POST" action="{{ route('deal-alerts.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="on" value="{{ $user->deal_alerts ? 0 : 1 }}">
                    <x-button type="submit" :variant="$user->deal_alerts ? 'outline' : 'primary'">
                        {{ $user->deal_alerts ? 'Turn off deal alerts' : 'Turn on deal alerts' }}
                    </x-button>
                </form>
            </div>
        </x-card>

        {{-- Shops followed with "Notify me next time" --}}
        <x-card as="section" class="mt-3">
            <h2 class="font-display text-xl font-bold">Shops you follow</h2>
            @if ($followedShops->isEmpty())
                <p class="mt-1 text-sm text-muted">None yet. Tap “Notify me next time” on a sold-out deal to hear when that shop posts again.</p>
            @else
                <ul class="mt-2 divide-y divide-line">
                    @foreach ($followedShops as $shop)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate font-semibold">{{ $shop->business_name }}</span>
                            <form method="POST" action="{{ route('shops.unfollow', $shop) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="outline">Stop<span class="sr-only"> notifications about {{ $shop->business_name }}</span></x-button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </x-layouts.customer>
@endif
