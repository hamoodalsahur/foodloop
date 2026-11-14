{{--
    A labelled multi-line text box with its error message.

    <x-form.textarea name="description" label="What customers might get" :value="$listing->description" rows="3" />
--}}
@props(['name', 'label', 'value' => null, 'hint' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-semibold">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}"
              @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
              {{ $attributes->except('id')->class([
                  'mt-1.5 block w-full rounded-xl border-2 bg-white px-3.5 py-3 text-base text-forest placeholder:text-muted',
                  'border-danger' => $hasError,
                  'border-forest' => ! $hasError,
              ]) }}>{{ old($name, $value) }}</textarea>
    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-danger">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @endif
</div>
