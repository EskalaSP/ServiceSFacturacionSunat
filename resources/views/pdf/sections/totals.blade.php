{{-- Tabla de totales para A4/A5 --}}
@if($tipo_documento !== '09' && $tipo_documento !== '31')
@php
    $moneda_prefix = $tipo_moneda !== 'PEN' ? $tipo_moneda : $moneda_simbolo;
    // La tasa de IGV viene del mapper ($igv_pct), tomada de porcentaje_igv de la
    // línea gravada (18, 10.5, 8...). Solo si no viene, se recalcula desde montos
    // como fallback (podría dar 17.99% por redondeo, pero es último recurso).
    if (isset($tipo_operacion) && str_starts_with($tipo_operacion, '02')) {
        $igv_pct = 0;
    } elseif (!empty($igv_pct)) {
        $igv_pct = ($igv_pct == floor($igv_pct)) ? (int) $igv_pct : rtrim(rtrim(number_format($igv_pct, 2, '.', ''), '0'), '.');
    } elseif (($mto_oper_gravadas ?? 0) > 0 && ($mto_igv ?? 0) > 0) {
        $igv_pct = round(($mto_igv / $mto_oper_gravadas) * 100, 2);
        // Redondeo cosmético: si es entero, sin decimales; si termina en .0, quita el 0
        $igv_pct = ($igv_pct == floor($igv_pct)) ? (int) $igv_pct : rtrim(rtrim(number_format($igv_pct, 2, '.', ''), '0'), '.');
    } else {
        $igv_pct = 0;
    }
@endphp
<table class="totals-table">
    @if($mto_oper_gravadas > 0)
    <tr>
        <td class="total-label">Op. Gravadas:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_oper_gravadas, 2) }}</td>
    </tr>
    @endif
    @if($mto_oper_exoneradas > 0)
    <tr>
        <td class="total-label">Op. Exoneradas:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_oper_exoneradas, 2) }}</td>
    </tr>
    @endif
    @if($mto_oper_inafectas > 0)
    <tr>
        <td class="total-label">Op. Inafectas:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_oper_inafectas, 2) }}</td>
    </tr>
    @endif
    @if($mto_oper_gratuitas > 0)
    <tr>
        <td class="total-label">Op. Gratuitas:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_oper_gratuitas, 2) }}</td>
    </tr>
    @endif
    @if(!empty($mto_base_ivap) && $mto_base_ivap > 0)
    <tr>
        <td class="total-label">Op. IVAP:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_base_ivap, 2) }}</td>
    </tr>
    <tr>
        <td class="total-label">IVAP (4%):</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_ivap, 2) }}</td>
    </tr>
    @endif
    @if($mto_igv > 0 || empty($mto_base_ivap) || $mto_base_ivap == 0)
    <tr>
        <td class="total-label">IGV ({{ $igv_pct }}%):</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_igv, 2) }}</td>
    </tr>
    @endif
    @if($mto_isc > 0)
    <tr>
        <td class="total-label">ISC:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_isc, 2) }}</td>
    </tr>
    @endif
    @if($mto_icbper > 0)
    <tr>
        <td class="total-label">ICBPER:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_icbper, 2) }}</td>
    </tr>
    @endif
    @if($total_descuentos > 0)
    <tr>
        <td class="total-label">Desc. Global:</td>
        <td class="total-value">-{{ $moneda_prefix }} {{ number_format($total_descuentos, 2) }}</td>
    </tr>
    @endif
    @if($total_anticipos > 0)
    <tr>
        <td class="total-label">Anticipos:</td>
        <td class="total-value">-{{ $moneda_prefix }} {{ number_format($total_anticipos, 2) }}</td>
    </tr>
    @endif
    @if(!empty($percepcion))
    <tr>
        <td class="total-label">Importe Total:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_imp_venta, 2) }}</td>
    </tr>
    <tr>
        <td class="total-label">Percepción ({{ $percepcion['porcentaje'] }}%):</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($percepcion['monto'], 2) }}</td>
    </tr>
    <tr class="total-final">
        <td class="total-label">IMPORTE TOTAL:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($percepcion['mto_total'], 2) }}</td>
    </tr>
    @else
    <tr class="total-final">
        <td class="total-label">IMPORTE TOTAL:</td>
        <td class="total-value">{{ $moneda_prefix }} {{ number_format($mto_imp_venta, 2) }}</td>
    </tr>
    @endif
</table>
<div style="clear: both;"></div>
@endif
