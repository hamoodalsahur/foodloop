{{--
    The two-column box of facts on a listing: pickup time, how many are left, halal status, allergens.
    Pass label => value pairs; an odd last one spans the full width.

    <x-spec-grid :items="[
        'Pickup' => '7–9 PM',
        'Left' => '3 of 5',
        'Halal' => 'Halal certified',
        'Contains' => 'Egg, milk, wheat',
    ]" />
--}}
@props(['items' => []])

@php($count = count($items))

<dl {{ $attributes->class('grid grid-cols-2 overflow-hidden rounded-2xl border-2 border-forest bg-white text-forest') }}>
    @foreach ($items as $label => $value)
        <div @class([
            'px-4 py-3',
            'border-l-2 border-forest' => $loop->index % 2 === 1,
            'border-t-2 border-forest' => $loop->index >= 2,
            'col-span-2' => $loop->last && $count % 2 === 1,
        ])>
            <dt class="text-[0.6875rem] font-bold uppercase tracking-wider text-muted">{{ $label }}</dt>
            <dd class="mt-0.5 font-display text-lg font-bold leading-snug">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
