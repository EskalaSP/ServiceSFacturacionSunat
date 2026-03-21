{{-- Header: Logo + Datos emisor --}}
@if($is_ticket)
    <div class="header-section">
        @if(!empty($logo_base64))
            <img src="{{ $logo_base64 }}" alt="Logo"><br>
        @endif
        <div class="emitter-name">{{ $emisor['razon_social'] }}</div>
        <div class="emitter-ruc">RUC: {{ $emisor['ruc'] }}</div>
        <div class="emitter-address">
            {{ $emisor['direccion'] }}
            @if($emisor['cod_local'] !== '0000')
                <br>Cod. Establecimiento: {{ $emisor['cod_local'] }}
            @endif
        </div>
        @if(!empty($telefonos))
        <div class="emitter-address">Tel: {{ implode(' | ', $telefonos) }}</div>
        @endif
        @if(!empty($emails))
        <div class="emitter-address">{{ implode(' | ', $emails) }}</div>
        @endif
    </div>
@else
    {{-- Renderizado como parte de header-table en layouts A4/A5 --}}
@endif
