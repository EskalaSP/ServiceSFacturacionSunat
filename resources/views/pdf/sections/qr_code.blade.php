{{-- Código QR --}}
@if(!empty($qr_base64))
    <div class="qr-section">
        <img src="{{ $qr_base64 }}" alt="QR">
    </div>
@endif
