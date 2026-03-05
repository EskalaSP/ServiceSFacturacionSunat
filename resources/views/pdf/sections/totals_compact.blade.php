{{-- Totales compactos para tickets --}}
@if($tipo_documento !== '09')
<div class="totals-section">
    @if($mto_oper_gravadas > 0)
    <div class="total-line">
        <span class="label">GRAVADA:</span>
        <span class="value">{{ number_format($mto_oper_gravadas, 2) }}</span>
    </div>
    @endif
    @if($mto_oper_exoneradas > 0)
    <div class="total-line">
        <span class="label">EXONERADA:</span>
        <span class="value">{{ number_format($mto_oper_exoneradas, 2) }}</span>
    </div>
    @endif
    @if($mto_oper_inafectas > 0)
    <div class="total-line">
        <span class="label">INAFECTA:</span>
        <span class="value">{{ number_format($mto_oper_inafectas, 2) }}</span>
    </div>
    @endif
    <div class="total-line">
        <span class="label">IGV:</span>
        <span class="value">{{ number_format($mto_igv, 2) }}</span>
    </div>
    @if($mto_icbper > 0)
    <div class="total-line">
        <span class="label">ICBPER:</span>
        <span class="value">{{ number_format($mto_icbper, 2) }}</span>
    </div>
    @endif
    @if($total_descuentos > 0)
    <div class="total-line">
        <span class="label">DESC:</span>
        <span class="value">-{{ number_format($total_descuentos, 2) }}</span>
    </div>
    @endif
    <div class="separator-dashed"></div>
    <div class="total-line total-final">
        <span class="label">TOTAL:</span>
        <span class="value">{{ $moneda_simbolo }} {{ number_format($mto_imp_venta, 2) }}</span>
    </div>
</div>
@endif
