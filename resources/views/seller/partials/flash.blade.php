{{-- The result of the last action: a success line, or why it was refused. --}}
@if (session('status'))
    <x-notice variant="success" class="mb-6">{{ session('status') }}</x-notice>
@endif
@error('listing')
    <x-notice variant="danger" class="mb-6">{{ $message }}</x-notice>
@enderror
