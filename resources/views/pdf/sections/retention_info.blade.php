<div class="info-section" style="margin: 8px 0; padding: 6px 8px; border: 0.5px solid #ccc; border-radius: 4px;">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:50%; vertical-align:top;">
                <strong>PROVEEDOR (RETENIDO)</strong><br>
                {{ $receptor['razon_social'] }}<br>
                RUC: {{ $receptor['num_doc'] }}
                @if(!empty($receptor['direccion']))
                <br>{{ $receptor['direccion'] }}
                @endif
            </td>
            <td style="width:50%; vertical-align:top; text-align:right;">
                <strong>Régimen:</strong> {{ $regimen_label }}<br>
                <strong>Tasa:</strong> {{ $tasa }}%<br>
                <strong>Fecha emisión:</strong> {{ $fecha_emision }}
            </td>
        </tr>
    </table>
    @if(!empty($observacion))
    <div style="margin-top:4px;"><strong>Observación:</strong> {{ $observacion }}</div>
    @endif
</div>
