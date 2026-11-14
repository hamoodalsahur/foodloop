{{--
    A labelled drop-down with its error message.

    <x-form.select name="category" label="Category" :options="['bakery' => 'Bakery', 'meals' => 'Meals']" :value="$listing->category?->value" />
--}}
@props(['name', 'label', 'options' => [], 'value' => null, 'hint' => null])

@php
    $id = $attributes->get('id', $name);
    $current = (string) old($name, $value);
    $hasError = $errors->has($name);
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-semibold">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}"
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->except('id')->class([
                'mt-1.5 block min-h-12 w-full rounded-xl border-2 bg-white px-3 text-base text-forest',
                'border-danger' => $hasError,
                'border-forest' => ! $hasError,
            ]) }}>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-danger">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @endif
</div>
