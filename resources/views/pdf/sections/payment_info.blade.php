{{-- Información de pago: forma de pago, cuotas, adelanto, detracción --}}
@if($tipo_documento === '01' || $tipo_documento === '03')
    @if($is_ticket)
        @if(!empty($forma_pago) || !empty($cuotas) || !empty($detraccion) || !empty($pagos))
        <div class="payment-section">
            @if(!empty($forma_pago))
            <div class="payment-title">Forma de Pago: {{ $forma_pago }}</div>
            @endif
            @if(!empty($pagos) && ($forma_pago ?? '') === 'Credito')
            <div class="info-line"><span class="info-label">Adelanto:</span> {{ $moneda_simbolo }} {{ number_format(collect($pagos)->sum('monto'), 2) }}</div>
            @endif
            @if(!empty($cuotas))
            <table class="payment-table-ticket">
                @foreach($cuotas as $cuota)
                <tr>
                    <td class="pay-label">Cuota {{ $loop->iteration }}:</td>
                    <td class="pay-value">{{ $cuota['fecha_pago'] ?? $cuota['fecha'] ?? '' }} - {{ $moneda_simbolo }} {{ number_format($cuota['monto'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </table>
            @endif
            @if(!empty($detraccion))
            <div style="border-top: 1px solid #000; margin-top: 4px; padding-top: 3px;">
                <div class="info-line"><span class="info-label">Detraccion:</span> {{ $detraccion['codigo'] ?? '' }} - {{ $detraccion['porcentaje'] ?? '' }}%</div>
                <div class="info-line"><span class="info-label">Monto:</span> {{ $moneda_simbolo }} {{ number_format($detraccion['monto'] ?? 0, 2) }}</div>
                @if(!empty($detraccion['cuenta']))
                <div class="info-line"><span class="info-label">Cuenta:</span> {{ $detraccion['cuenta'] }}</div>
                @endif
            </div>
            @endif
            @if(!empty($percepcion))
            <div style="border-top: 1px solid #000; margin-top: 4px; padding-top: 3px;">
                <div class="info-line"><span class="info-label">Percepcion:</span> {{ $percepcion['codigo'] ?? '' }} - {{ $percepcion['porcentaje'] ?? '' }}%</div>
                <div class="info-line"><span class="info-label">Monto:</span> {{ $moneda_simbolo }} {{ number_format($percepcion['monto'] ?? 0, 2) }}</div>
            </div>
            @endif
        </div>
        @endif
    @else
        @if(!empty($forma_pago) || !empty($cuotas) || !empty($detraccion) || !empty($pagos))
        <div class="payment-section">
            <table>
                @if(!empty($forma_pago))
                <tr>
                    <td style="width: 140px;"><strong>Forma de Pago:</strong></td>
                    <td>{{ $forma_pago }}</td>
                </tr>
                @endif
                @if(!empty($pagos) && ($forma_pago ?? '') === 'Credito')
                <tr>
                    <td><strong>Adelanto:</strong></td>
                    <td>{{ $moneda_simbolo }} {{ number_format(collect($pagos)->sum('monto'), 2) }}</td>
                </tr>
                @endif
                @if(!empty($cuotas))
                    @foreach($cuotas as $cuota)
                    <tr>
                        <td><strong>{{ $loop->first ? 'Cuotas:' : '' }}</strong></td>
                        <td>{{ $cuota['fecha_pago'] ?? $cuota['fecha'] ?? '' }} — {{ $moneda_simbolo }} {{ number_format($cuota['monto'] ?? 0, 2) }}</td>
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
