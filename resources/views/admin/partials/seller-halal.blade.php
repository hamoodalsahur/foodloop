{{-- A shop's halal claim, with a link to the certificate when one was uploaded. Pass `seller`. --}}
@php($certified = $seller->halal_claim === \App\Enums\HalalStatus::Certified)

@if ($certified && filled($seller->halal_cert_path))
    Certificate uploaded ·
    <a href="{{ route('admin.sellers.certificate', $seller) }}" target="_blank" rel="noopener"
       class="font-semibold underline underline-offset-4">View<span class="sr-only"> {{ $seller->business_name }}’s certificate</span></a>
@elseif ($certified)
    <strong class="text-danger">Says certified, no file</strong>
@else
    {{ $seller->halal_claim->label() }}
@endif
