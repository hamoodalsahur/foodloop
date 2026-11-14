{{-- A shop's SSM number, or a red "Missing". Pass `seller`. --}}
@if (filled($seller->ssm_no))
    {{ $seller->ssm_no }}
@else
    <strong class="text-danger">Missing</strong>
@endif
