{{-- Información de pago: forma de pago, cuotas, detracción, percepción --}}
@php
    $guia_tipo_map = ['09' => 'Guía de Remisión', '31' => 'Guía de Remisión Transportista', '71' => 'Guía de Remisión Remitente'];
@endphp
@if($tipo_documento === '01' || $tipo_documento === '03')
    @if($is_ticket)
        @if(!empty($forma_pago) || !empty($cuotas) || !empty($detraccion) || !empty($pagos) || !empty($guias))
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
            @if(!empty($guias))
            <div style="border-top: 1px solid #000; margin-top: 4px; padding-top: 3px;">
                @foreach($guias as $g)
                <div class="info-line"><span class="info-label">{{ $guia_tipo_map[$g['tipo_doc']] ?? 'Guía' }}:</span> {{ $g['nro_doc'] }}</div>
                @endforeach
            </div>
            @endif
        </div>
        @endif
    @else
        @php
            $tieneDetraccion = !empty($detraccion);
            $tieneCredito    = !empty($cuotas) || (($forma_pago ?? '') === 'Credito');
            $tieneGuias      = !empty($guias);
            $montoNeto       = $tieneDetraccion
                ? round($mto_imp_venta - ($detraccion['monto'] ?? 0), 2)
                : $mto_imp_venta;
        @endphp
        @if($tieneDetraccion || $tieneCredito || !empty($forma_pago) || !empty($pagos) || $tieneGuias || !empty($percepcion))
        <div class="payment-section">

            {{-- ── Forma de Pago ───────────────────────────────── --}}
            @if(!empty($forma_pago))
            <table style="margin-bottom: 6px;">
                <tr>
                    <td style="width: 180px;"><strong>Forma de Pago:</strong></td>
                    <td>{{ $forma_pago }}</td>
                </tr>
                @if(!empty($pagos) && ($forma_pago ?? '') === 'Credito')
                <tr>
                    <td><strong>Adelanto:</strong></td>
                    <td>{{ $moneda_simbolo }} {{ number_format(collect($pagos)->sum('monto'), 2) }}</td>
                </tr>
                @endif
            </table>
            @endif

            {{-- ── Detracción ───────────────────────────────────── --}}
            @if($tieneDetraccion)
            <table style="border-top: 0.5px solid #000; padding-top: 5px; margin-top: 5px; width: 100%;">
                <tr>
                    <td style="width: 180px;"><strong>Detracción:</strong></td>
                    <td>
                        Código: {{ $detraccion['codigo'] ?? '' }}
                        @if(!empty($detraccion['porcentaje'])) | {{ $detraccion['porcentaje'] }}% @endif
                        | Monto: {{ $moneda_simbolo }} {{ number_format($detraccion['monto'] ?? 0, 2) }}
                        @if(!empty($detraccion['cuenta'])) | Cta. BN: {{ $detraccion['cuenta'] }} @endif
                    </td>
                </tr>
                <tr>
                    <td style="width: 180px;"><strong>Mto. Neto Pendiente de Pago:</strong></td>
                    <td><strong>{{ $moneda_simbolo }} {{ number_format($montoNeto, 2) }}</strong></td>
                </tr>
            </table>
            @endif

            {{-- ── Información del Crédito ─────────────────────── --}}
            @if($tieneCredito && !empty($cuotas))
            <table style="border-top: 0.5px solid #000; padding-top: 5px; margin-top: 5px; width: 100%;">
                <tr>
                    <td colspan="3" style="padding-bottom: 4px;"><strong>Información del Crédito</strong></td>
                </tr>
                <tr>
                    <td style="width: 180px;"><strong>Total de Cuotas:</strong></td>
                    <td colspan="2">{{ count($cuotas) }}</td>
                </tr>
            </table>
            <table style="margin-top: 4px; width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5; border-bottom: 0.5px solid #000;">
                        <th style="padding: 3px 6px; text-align: center; font-size: 8pt; width: 60px;">Nº Cuota</th>
                        <th style="padding: 3px 6px; text-align: center; font-size: 8pt; width: 110px;">Fec. Vencimiento</th>
                        <th style="padding: 3px 6px; text-align: right; font-size: 8pt;">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cuotas as $cuota)
                    <tr style="border-bottom: 0.5px solid #ddd;">
                        <td style="padding: 3px 6px; text-align: center; font-size: 8.5pt;">{{ $loop->iteration }}</td>
                        <td style="padding: 3px 6px; text-align: center; font-size: 8.5pt;">{{ $cuota['fecha_pago'] ?? $cuota['fecha'] ?? '' }}</td>
                        <td style="padding: 3px 6px; text-align: right; font-size: 8.5pt;">{{ $moneda_simbolo }} {{ number_format($cuota['monto'] ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            {{-- ── Percepción ───────────────────────────────────── --}}
            @if(!empty($percepcion))
            <table style="border-top: 0.5px solid #000; padding-top: 5px; margin-top: 5px; width: 100%;">
                <tr>
                    <td style="width: 180px;"><strong>Percepción:</strong></td>
                    <td>
                        {{ $percepcion['codigo'] ?? '' }}
                        @if(!empty($percepcion['porcentaje'])) | {{ $percepcion['porcentaje'] }}% @endif
                        | Monto: {{ $moneda_simbolo }} {{ number_format($percepcion['monto'] ?? 0, 2) }}
                    </td>
                </tr>
            </table>
            @endif

            {{-- ── Guías de Remisión ────────────────────────────── --}}
            @if($tieneGuias)
            <table style="border-top: 0.5px solid #000; padding-top: 5px; margin-top: 5px; width: 100%;">
                @foreach($guias as $g)
                <tr>
                    <td style="width: 180px;"><strong>{{ $loop->first ? 'Guías de Remisión:' : '' }}</strong></td>
                    <td>{{ $guia_tipo_map[$g['tipo_doc']] ?? 'Guía' }} — {{ $g['nro_doc'] }}</td>
                </tr>
                @endforeach
            </table>
            @endif

        </div>
        @endif
    @endif
@endif
