{{-- Información de pago: forma de pago, cuotas, detracción --}}
@if($tipo_documento === '01' || $tipo_documento === '03')
    @if($is_ticket)
        <div class="payment-section">
            @if(!empty($forma_pago))
            <div><span class="info-label">Pago:</span> {{ $forma_pago }}</div>
            @endif
            @if(!empty($cuotas))
                @foreach($cuotas as $cuota)
                <div>{{ $cuota['fecha'] ?? '' }} - {{ $moneda_simbolo }} {{ number_format($cuota['monto'] ?? 0, 2) }}</div>
                @endforeach
            @endif
            @if(!empty($detraccion))
            <div class="separator-dashed"></div>
            <div><span class="info-label">Detracción:</span></div>
            <div>{{ $detraccion['codigo'] ?? '' }} - {{ $detraccion['porcentaje'] ?? '' }}%</div>
            <div>Monto: {{ $moneda_simbolo }} {{ number_format($detraccion['monto'] ?? 0, 2) }}</div>
            @if(!empty($detraccion['cuenta']))
            <div>Cta: {{ $detraccion['cuenta'] }}</div>
            @endif
            @endif
        </div>
    @else
        @if(!empty($forma_pago) || !empty($cuotas) || !empty($detraccion))
        <div class="payment-section">
            <table>
                @if(!empty($forma_pago))
                <tr>
                    <td style="width: 140px;"><strong>Forma de Pago:</strong></td>
                    <td>{{ $forma_pago }}</td>
                </tr>
                @endif
                @if(!empty($cuotas))
                    @foreach($cuotas as $cuota)
                    <tr>
                        <td><strong>{{ $loop->first ? 'Cuotas:' : '' }}</strong></td>
                        <td>{{ $cuota['fecha'] ?? '' }} — {{ $moneda_simbolo }} {{ number_format($cuota['monto'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                @endif
                @if(!empty($detraccion))
                <tr>
                    <td><strong>Detracción:</strong></td>
                    <td>
                        Código: {{ $detraccion['codigo'] ?? '' }} |
                        {{ $detraccion['porcentaje'] ?? '' }}% |
                        Monto: {{ $moneda_simbolo }} {{ number_format($detraccion['monto'] ?? 0, 2) }}
                        @if(!empty($detraccion['cuenta']))
                        | Cuenta: {{ $detraccion['cuenta'] }}
                        @endif
                    </td>
                </tr>
                @endif
                @if(!empty($percepcion))
                <tr>
                    <td><strong>Percepción:</strong></td>
                    <td>
                        {{ $percepcion['codigo'] ?? '' }} |
                        {{ $percepcion['porcentaje'] ?? '' }}% |
                        Monto: {{ $moneda_simbolo }} {{ number_format($percepcion['monto'] ?? 0, 2) }}
                    </td>
                </tr>
                @endif
            </table>
        </div>
        @endif
    @endif
@endif
