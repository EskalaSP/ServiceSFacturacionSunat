{{-- Header: Logo + Datos emisor (usado dentro del layout) --}}
@if($is_ticket)
    <div class="header-section">
        @if(!empty($logo_base64))
            <img src="{{ $logo_base64 }}" alt="Logo"><br>
        @endif
        <div class="emitter-name">{{ $emisor['razon_social'] }}</div>
        <div class="emitter-ruc">RUC: {{ $emisor['ruc'] }}</div>
        <div class="emitter-address">{{ $emisor['direccion'] }}</div>
    </div>
@else
    {{-- Renderizado como parte de header-table en layouts A4/A5 --}}
@endif
