{{-- Tabla de totales para A4/A5 --}}
@if($tipo_documento !== '09')
<table class="totals-table">
    @if($mto_oper_gravadas > 0)
    <tr>
        <td class="total-label">Op. Gravadas:</td>
        <td class="total-value">{{ number_format($mto_oper_gravadas, 2) }}</td>
    </tr>
    @endif
    @if($mto_oper_exoneradas > 0)
    <tr>
        <td class="total-label">Op. Exoneradas:</td>
        <td class="total-value">{{ number_format($mto_oper_exoneradas, 2) }}</td>
    </tr>
    @endif
    @if($mto_oper_inafectas > 0)
    <tr>
        <td class="total-label">Op. Inafectas:</td>
        <td class="total-value">{{ number_format($mto_oper_inafectas, 2) }}</td>
    </tr>
    @endif
    @if($mto_oper_gratuitas > 0)
    <tr>
        <td class="total-label">Op. Gratuitas:</td>
        <td class="total-value">{{ number_format($mto_oper_gratuitas, 2) }}</td>
    </tr>
    @endif
    <tr>
        <td class="total-label">IGV (18%):</td>
        <td class="total-value">{{ number_format($mto_igv, 2) }}</td>
    </tr>
    @if($mto_isc > 0)
    <tr>
        <td class="total-label">ISC:</td>
        <td class="total-value">{{ number_format($mto_isc, 2) }}</td>
    </tr>
    @endif
    @if($mto_icbper > 0)
    <tr>
        <td class="total-label">ICBPER:</td>
        <td class="total-value">{{ number_format($mto_icbper, 2) }}</td>
    </tr>
    @endif
    @if($total_descuentos > 0)
    <tr>
        <td class="total-label">Descuentos:</td>
        <td class="total-value">-{{ number_format($total_descuentos, 2) }}</td>
    </tr>
    @endif
    @if($total_anticipos > 0)
    <tr>
        <td class="total-label">Anticipos:</td>
        <td class="total-value">-{{ number_format($total_anticipos, 2) }}</td>
    </tr>
    @endif
    <tr class="total-final">
        <td class="total-label">TOTAL VENTA:</td>
        <td class="total-value">{{ $moneda_simbolo }} {{ number_format($mto_imp_venta, 2) }}</td>
    </tr>
</table>
<div style="clear: both;"></div>
@endif
