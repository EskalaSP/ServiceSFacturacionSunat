{{-- Leyenda: monto en letras --}}
@if(!empty($leyenda) && $tipo_documento !== '09')
    @if($is_ticket)
        <div class="leyenda">SON: {{ $leyenda }}</div>
    @else
        <div class="leyenda">SON: {{ $leyenda }}</div>
    @endif
@endif
