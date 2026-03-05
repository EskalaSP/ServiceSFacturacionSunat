{{-- Footer: hash, estado SUNAT, observación --}}
@if($is_ticket)
    <div class="footer-section">
        @if(!empty($hash_cpe))
        <div>Hash: {{ $hash_cpe }}</div>
        @endif
        @if(!empty($observacion))
        <div>{{ $observacion }}</div>
        @endif
        <div style="margin-top: 4px;">Representación impresa del comprobante electrónico</div>
    </div>
@else
    <div class="footer-section">
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
