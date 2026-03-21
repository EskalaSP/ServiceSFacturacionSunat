@if(!empty($cuentas_bancarias) || !empty($billeteras_digitales))
@if($is_ticket)
    <div class="payment-section">
        <div class="payment-title">Cuentas para depósito / transferencia</div>
        @foreach($cuentas_bancarias as $cuenta)
        <div class="info-line">
            {{ $cuenta['banco'] }} ({{ $cuenta['moneda'] ?? 'PEN' }}) {{ $cuenta['tipo_cuenta'] }}: {{ $cuenta['numero'] }}
            @if(!empty($cuenta['cci'])) | CCI: {{ $cuenta['cci'] }} @endif
        </div>
        @endforeach
        @foreach($billeteras_digitales as $billetera)
        <div class="info-line">
            {{ ucfirst($billetera['tipo']) }}: {{ $billetera['numero'] }}
            @if(!empty($billetera['titular'])) ({{ $billetera['titular'] }}) @endif
        </div>
        @endforeach
    </div>
@else
    <div class="payment-section">
        <div style="margin-bottom: 4px;"><strong>Cuentas para depósito / transferencia:</strong></div>
        <table>
            @foreach($cuentas_bancarias as $cuenta)
            <tr>
                <td style="width: 80px;">{{ $cuenta['banco'] }}</td>
                <td>{{ $cuenta['tipo_cuenta'] }} ({{ $cuenta['moneda'] ?? 'PEN' }}): {{ $cuenta['numero'] }}</td>
                <td>@if(!empty($cuenta['cci'])) CCI: {{ $cuenta['cci'] }} @endif</td>
            </tr>
            @endforeach
        </table>
        @if(!empty($billeteras_digitales))
        <div style="margin-top: 4px;">
            @foreach($billeteras_digitales as $billetera)
            <strong>{{ ucfirst($billetera['tipo']) }}:</strong> {{ $billetera['numero'] }}
            @if(!empty($billetera['titular'])) ({{ $billetera['titular'] }}) @endif
            @if(!$loop->last) &nbsp;|&nbsp; @endif
            @endforeach
        </div>
        @endif
    </div>
@endif
@endif
