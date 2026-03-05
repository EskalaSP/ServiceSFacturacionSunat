{{-- Información del emisor (complemento, para tickets ya se muestra en header) --}}
@if(!$is_ticket)
    {{-- En A4/A5 la info del emisor va en el header-table --}}
@else
    @if($emisor['cod_local'] !== '0000')
        <div class="info-line">Cod. Local: {{ $emisor['cod_local'] }}</div>
    @endif
@endif
