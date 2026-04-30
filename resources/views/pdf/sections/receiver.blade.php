{{-- Datos del receptor/cliente --}}
@php
    $tipoDocLabel = match($receptor['tipo_doc']) {
        '6' => 'RUC',
        '1' => 'DNI',
        '4' => $is_ticket ? 'C.E.' : 'Carnet Extranjería',
        '7' => 'Pasaporte',
        default => $is_ticket ? 'Doc' : 'Documento',
    };
@endphp

@if($is_ticket)
    <div class="info-block">
        <div class="info-line"><span class="info-label">Cliente:</span> {{ $receptor['razon_social'] }}</div>
        <div class="info-line"><span class="info-label">{{ $tipoDocLabel }}:</span> {{ $receptor['num_doc'] }}</div>
        @if(!empty($receptor['direccion']))
            <div class="info-line"><span class="info-label">Dir.:</span> {{ $receptor['direccion'] }}</div>
        @endif
        <div class="info-line"><span class="info-label">Moneda:</span> {{ $tipo_moneda }} ({{ $moneda_simbolo }})</div>
    </div>
@else
    <table class="info-section">
        <tr>
            <td class="info-label">Señor(es)</td>
            <td class="info-value" style="text-transform: uppercase;">: {{ $receptor['razon_social'] }}</td>
            <td class="info-label" style="padding-left: 12px;">Fecha Emisión</td>
            <td class="info-value">: {{ $fecha_emision }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ $tipoDocLabel }}</td>
            <td class="info-value-mono">: {{ $receptor['num_doc'] ?? '' }}</td>
            <td class="info-label" style="padding-left: 12px;">Tipo de Moneda</td>
            <td class="info-value">: {{ $tipo_moneda }}</td>
        </tr>
        <tr>
            <td class="info-label">Forma de Pago</td>
            <td class="info-value">: {{ $forma_pago ?? 'Contado' }}</td>
            @if(!empty($fecha_vencimiento))
            <td class="info-label" style="padding-left: 12px;">Fec. Vencimiento</td>
            <td class="info-value">: {{ $fecha_vencimiento }}</td>
            @else
            <td colspan="2"></td>
            @endif
        </tr>
        @if(!empty($receptor['direccion']))
        <tr>
            <td class="info-label">Dirección</td>
            <td colspan="3" class="info-value">: {{ $receptor['direccion'] }}</td>
        </tr>
        @endif
    </table>
@endif
