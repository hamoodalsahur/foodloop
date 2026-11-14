{{-- The round arrow badge inside a call-to-action button. Decorative. --}}
@props(['on' => 'primary'])

<span aria-hidden="true"
      class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $on === 'marigold' ? 'bg-forest text-marigold' : 'bg-marigold text-forest' }}">
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4">
        <path d="M4 10h12M11 5l5 5-5 5" />
    </svg>
</span>
