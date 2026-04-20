@if($is_ticket)
    <div class="totals-section">
        <table class="totals-table-ticket">
            <tr class="total-separator">
                <td class="total-label">Imp. Retenido</td>
                <td class="total-value">S/ {{ number_format($imp_retenido, 2) }}</td>
            </tr>
            <tr class="total-final">
                <td class="total-label">Imp. a Pagar</td>
                <td class="total-value">S/ {{ number_format($imp_pagado, 2) }}</td>
            </tr>
        </table>
    </div>
@else
    <table class="totals-table">
        <tr class="total-separator">
            <td class="total-label">Total Imp. Retenido:</td>
            <td class="total-value">S/ {{ number_format($imp_retenido, 2) }}</td>
        </tr>
        <tr class="total-final">
            <td class="total-label">Total Imp. a Pagar:</td>
            <td class="total-value">S/ {{ number_format($imp_pagado, 2) }}</td>
        </tr>
    </table>
    <div style="clear: both;"></div>
@endif
