{{--
    The bell: a link to the signed-in person's notifications, with how many are new.
    Shows nothing for guests.

    <x-bell />               on a light background (customer header)
    <x-bell tone="dark" />   on the forest sidebar (seller and admin)
--}}
@props(['tone' => 'light'])

@auth
    {{-- If the count can't be read, show the bell without a number rather than break the page. --}}
    @php
        $unread = rescue(fn () => auth()->user()->unreadNotifications()->count(), 0);
    @endphp

    <a href="{{ route('notifications.index') }}"
       {{ $attributes->class([
           'relative flex size-11 shrink-0 items-center justify-center rounded-full',
           'border-2 border-forest bg-white text-forest hover:bg-cream' => $tone === 'light',
           'border border-cream/30 text-cream hover:bg-cream/10' => $tone === 'dark',
       ]) }}>
        <x-icon.bell class="size-5" />
        <span class="sr-only">Notifications{{ $unread > 0 ? ', '.$unread.' new' : '' }}</span>
        @if ($unread > 0)
            <span aria-hidden="true" @class([
                'absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-marigold px-1 text-[0.6875rem] font-bold leading-none text-forest ring-2',
                'ring-cream' => $tone === 'light',
                'ring-forest' => $tone === 'dark',
            ])>{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </a>
@endauth
