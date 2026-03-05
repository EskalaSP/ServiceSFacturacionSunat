{{-- Datos del receptor/cliente --}}
@if($is_ticket)
    <div class="separator-dashed"></div>
    <div class="info-line"><span class="info-label">Cliente:</span> {{ $receptor['razon_social'] }}</div>
    @php
        $tipoDocLabel = match($receptor['tipo_doc']) {
            '6' => 'RUC',
            '1' => 'DNI',
            '4' => 'C.E.',
            '7' => 'Pasaporte',
            default => 'Doc',
        };
    @endphp
    <div class="info-line"><span class="info-label">{{ $tipoDocLabel }}:</span> {{ $receptor['num_doc'] }}</div>
    @if(!empty($receptor['direccion']))
        <div class="info-line">{{ $receptor['direccion'] }}</div>
    @endif
    <div class="separator-dashed"></div>
@else
    <table class="info-section">
        @php
            $tipoDocLabel = match($receptor['tipo_doc']) {
                '6' => 'RUC',
                '1' => 'DNI',
                '4' => 'Carnet Extranjería',
                '7' => 'Pasaporte',
                default => 'Documento',
            };
        @endphp
        <tr>
            <td class="info-label">Cliente:</td>
            <td>{{ $receptor['razon_social'] }}</td>
            <td class="info-label">Fecha Emisión:</td>
            <td>{{ $fecha_emision }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ $tipoDocLabel }}:</td>
            <td>{{ $receptor['num_doc'] }}</td>
            <td class="info-label">Moneda:</td>
            <td>{{ $tipo_moneda }}</td>
        </tr>
        @if(!empty($receptor['direccion']))
        <tr>
            <td class="info-label">Dirección:</td>
            <td colspan="3">{{ $receptor['direccion'] }}</td>
        </tr>
        @endif
        @if(!empty($fecha_vencimiento))
        <tr>
            <td class="info-label">Fecha Vencimiento:</td>
            <td colspan="3">{{ $fecha_vencimiento }}</td>
        </tr>
        @endif
    </table>
@endif
