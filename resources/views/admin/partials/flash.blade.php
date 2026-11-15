{{-- The result of the last admin action: what happened, or why it was refused. --}}
@if (session('status'))
    <x-notice variant="success" class="mb-6">{{ session('status') }}</x-notice>
@endif
@foreach (['admin', 'question', 'outcome'] as $field)
    @error($field)
        <x-notice variant="danger" class="mb-6">{{ $message }}</x-notice>
    @enderror
@endforeach
