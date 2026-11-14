{{--
    A listing's picture, or a quiet placeholder when there is none.
    `path` is a path on the public disk (what Listing::photo_path holds).

    <x-food-photo :path="$listing->photo_path" :alt="$listing->title" />              thumbnail (default)
    <x-food-photo :path="$listing->photo_path" :alt="$listing->title" size="hero" />  top of the listing page
    <x-food-photo :path="null" alt="No photo yet" size="sm" />
--}}
@props([
    'path' => null,
    'alt' => '',
    'size' => 'md', // sm (48 px) | md (80 px) | hero (full width)
])

@php
    $sizes = [
        'sm' => 'size-12 rounded-xl',
        'md' => 'size-20 rounded-2xl',
        'hero' => 'aspect-[4/3] w-full sm:aspect-[16/9]',
    ];
    $box = $sizes[$size] ?? $sizes['md'];
@endphp

@if ($path)
    <img src="{{ asset('storage/'.$path) }}"
         alt="{{ $alt }}"
         loading="lazy"
         {{ $attributes->class([$box, 'shrink-0 bg-line object-cover']) }}>
@else
    <div role="img" aria-label="{{ $alt ?: 'No photo' }}"
         {{ $attributes->class([$box, 'flex shrink-0 items-center justify-center bg-line text-muted']) }}>
        {{-- A plate with a fork and spoon --}}
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-1/2 max-h-24" aria-hidden="true">
            <circle cx="24" cy="24" r="11" />
            <circle cx="24" cy="24" r="6" />
            <path d="M6 12v9a3 3 0 0 0 3 3v12M6 12v7M9 12v7M12 12v9a3 3 0 0 1-3 3M41 12c-3 2-4 6-4 10h4zm0 0v24" />
        </svg>
    </div>
@endif
