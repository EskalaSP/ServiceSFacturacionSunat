{{-- Footer: hash, estado SUNAT, observación --}}
@if($is_ticket)
    <div class="footer-section">
        @if(!empty($mensaje_agradecimiento))
        <div style="margin-bottom: 3px; font-style: italic;">{{ $mensaje_agradecimiento }}</div>
        @endif
        @if(!empty($mensaje_promocional))
        <div style="margin-bottom: 3px;">{{ $mensaje_promocional }}</div>
        @endif
        @if(!empty($hash_cpe))
        <div class="footer-hash">Hash: {{ $hash_cpe }}</div>
        @endif
        @if(!empty($observacion))
        <div style="margin-bottom: 3px;">{{ $observacion }}</div>
        @endif
        <div class="footer-legal">Representación impresa del<br>comprobante electrónico</div>
    </div>
@else
    <div class="footer-section">
        @if(!empty($mensaje_agradecimiento))
        <div style="margin-bottom: 4px; font-style: italic;">{{ $mensaje_agradecimiento }}</div>
        @endif
        @if(!empty($mensaje_promocional))
        <div style="margin-bottom: 4px;">{{ $mensaje_promocional }}</div>
        @endif
        <table>
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    @if(!empty($hash_cpe))
                    <strong>Hash CPE:</strong> {{ $hash_cpe }}
                    @endif
                    @if(!empty($observacion))
                    <br><strong>Obs:</strong> {{ $observacion }}
                    @endif
                </td>
                <td style="text-align: right; vertical-align: top;">
                    Representación impresa del comprobante electrónico
                </td>
            </tr>
        </table>
    </div>
@endif
