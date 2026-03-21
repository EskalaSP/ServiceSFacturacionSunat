{{-- Totales completos para tickets (tabla) --}}
@if($tipo_documento !== '09')
<div class="totals-section">
    <table class="totals-table-ticket">
        @if($mto_oper_gravadas > 0)
        <tr>
            <td class="total-label">Op. Gravadas</td>
            <td class="total-value">{{ number_format($mto_oper_gravadas, 2) }}</td>
        </tr>
        @endif
        @if($mto_oper_exoneradas > 0)
        <tr>
            <td class="total-label">Op. Exoneradas</td>
            <td class="total-value">{{ number_format($mto_oper_exoneradas, 2) }}</td>
        </tr>
        @endif
        @if($mto_oper_inafectas > 0)
        <tr>
            <td class="total-label">Op. Inafectas</td>
            <td class="total-value">{{ number_format($mto_oper_inafectas, 2) }}</td>
        </tr>
        @endif
        @if($mto_oper_gratuitas > 0)
        <tr>
            <td class="total-label">Op. Gratuitas</td>
            <td class="total-value">{{ number_format($mto_oper_gratuitas, 2) }}</td>
        </tr>
        @endif
        <tr class="total-separator">
            <td class="total-label">IGV (18%)</td>
            <td class="total-value">{{ number_format($mto_igv, 2) }}</td>
        </tr>
        @if($mto_isc > 0)
        <tr>
            <td class="total-label">ISC</td>
            <td class="total-value">{{ number_format($mto_isc, 2) }}</td>
        </tr>
        @endif
        @if($mto_icbper > 0)
        <tr>
            <td class="total-label">ICBPER</td>
            <td class="total-value">{{ number_format($mto_icbper, 2) }}</td>
        </tr>
        @endif
        @if($total_descuentos > 0)
        <tr>
            <td class="total-label">Desc. Global</td>
            <td class="total-value">-{{ number_format($total_descuentos, 2) }}</td>
        </tr>
        @endif
        @if($total_anticipos > 0)
        <tr>
            <td class="total-label">Anticipos</td>
            <td class="total-value">-{{ number_format($total_anticipos, 2) }}</td>
        </tr>
        @endif
        @if(!empty($percepcion))
        <tr>
            <td class="total-label">Subtotal</td>
            <td class="total-value">{{ $moneda_simbolo }} {{ number_format($mto_imp_venta, 2) }}</td>
        </tr>
        <tr>
            <td class="total-label">Percepción ({{ $percepcion['porcentaje'] }}%)</td>
            <td class="total-value">{{ number_format($percepcion['monto'], 2) }}</td>
        </tr>
        <tr class="total-final">
            <td class="total-label">TOTAL</td>
            <td class="total-value">{{ $moneda_simbolo }} {{ number_format($percepcion['mto_total'], 2) }}</td>
        </tr>
        @else
        <tr class="total-final">
            <td class="total-label">TOTAL</td>
            <td class="total-value">{{ $moneda_simbolo }} {{ number_format($mto_imp_venta, 2) }}</td>
        </tr>
        @endif
    </table>
</div>
@endif
