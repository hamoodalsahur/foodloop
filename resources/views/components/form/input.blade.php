{{--
    A labelled text field with its error message.

    <x-form.input name="email" label="Email" type="email" autocomplete="username" required />
    <x-form.input name="password" label="Password" type="password" hint="At least 8 characters." />

    The old value is filled back in after a failed submit (never for passwords).
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'value' => null,
])

@php
    $id = $attributes->get('id', $name);
    $value = $type === 'password' ? null : old($name, $value);
    $hasError = $errors->has($name);
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-semibold">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if ($value !== null) value="{{ $value }}" @endif
           @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
           {{ $attributes->except('id')->class([
               'mt-1.5 block min-h-12 w-full rounded-xl border-2 bg-white px-3.5 text-base text-forest placeholder:text-muted',
               'border-danger' => $hasError,
               'border-forest' => ! $hasError,
           ]) }}>
    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-danger">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-sm text-muted">{{ $hint }}</p>
    @endif
</div>
