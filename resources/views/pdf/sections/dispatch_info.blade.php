{{-- Información de despacho (Guía de Remisión) --}}
@if(!empty($dispatch))
    @if($is_ticket)
        <div class="dispatch-section">
            <div class="section-title">Datos del Traslado</div>
            <div class="info-line"><span class="info-label">Fecha:</span> {{ $dispatch['fecha_traslado'] }}</div>
            <div class="info-line"><span class="info-label">Motivo:</span> {{ $dispatch['cod_traslado'] }}</div>
            <div class="info-line"><span class="info-label">Modalidad:</span> {{ $dispatch['mod_traslado'] }}</div>
            <div class="info-line"><span class="info-label">Peso:</span> {{ number_format($dispatch['peso_total'], 3) }} {{ $dispatch['und_peso_total'] }}</div>
            @if($dispatch['num_bultos'])
            <div class="info-line"><span class="info-label">Bultos:</span> {{ $dispatch['num_bultos'] }}</div>
            @endif

            <div class="section-title">Partida / Llegada</div>
            <div class="info-line"><span class="info-label">De:</span> [{{ $dispatch['partida_ubigeo'] }}] {{ $dispatch['partida_direccion'] }}</div>
            <div class="info-line"><span class="info-label">A:</span> [{{ $dispatch['llegada_ubigeo'] }}] {{ $dispatch['llegada_direccion'] }}</div>

            @if(!empty($dispatch['transportista']['razon_social']))
            <div class="section-title">Transportista</div>
            <div class="info-line">{{ $dispatch['transportista']['razon_social'] }}</div>
            <div class="info-line"><span class="info-label">Doc:</span> {{ $dispatch['transportista']['num_doc'] }}</div>
            @endif

            @if(!empty($dispatch['vehiculo']['placa']))
            <div class="info-line"><span class="info-label">Placa:</span> {{ $dispatch['vehiculo']['placa'] }}</div>
            @endif

            @if(!empty($dispatch['conductor']))
            <div class="section-title">Conductor(es)</div>
            @foreach($dispatch['conductor'] as $c)
            <div class="info-line">{{ $c['nombres'] }} {{ $c['apellidos'] }} — {{ $c['num_doc'] }}</div>
            @if(!empty($c['licencia']))
            <div class="info-line"><span class="info-label">Lic:</span> {{ $c['licencia'] }}</div>
            @endif
            @endforeach
            @endif
        </div>
    @else
        <div class="dispatch-section">
            <div class="section-title">Datos del Traslado</div>
            <table class="info-section">
                <tr>
                    <td class="info-label">Fecha Traslado:</td>
                    <td>{{ $dispatch['fecha_traslado'] }}</td>
                    <td class="info-label">Motivo Traslado:</td>
                    <td>{{ $dispatch['cod_traslado'] }}</td>
                </tr>
                <tr>
                    <td class="info-label">Modalidad:</td>
                    <td>{{ $dispatch['mod_traslado'] }}</td>
                    <td class="info-label">Peso Total:</td>
                    <td>{{ number_format($dispatch['peso_total'], 3) }} {{ $dispatch['und_peso_total'] }}</td>
                </tr>
                @if($dispatch['num_bultos'])
                <tr>
                    <td class="info-label">Num. Bultos:</td>
                    <td colspan="3">{{ $dispatch['num_bultos'] }}</td>
                </tr>
                @endif
            </table>

            <div class="section-title">Punto de Partida / Llegada</div>
            <table class="info-section">
                <tr>
                    <td class="info-label">Partida:</td>
                    <td>[{{ $dispatch['partida_ubigeo'] }}] {{ $dispatch['partida_direccion'] }}</td>
                </tr>
                <tr>
                    <td class="info-label">Llegada:</td>
                    <td>[{{ $dispatch['llegada_ubigeo'] }}] {{ $dispatch['llegada_direccion'] }}</td>
                </tr>
            </table>

            @if(!empty($dispatch['transportista']['razon_social']))
            <div class="section-title">Transportista</div>
            <table class="info-section">
                <tr>
                    <td class="info-label">Razón Social:</td>
                    <td>{{ $dispatch['transportista']['razon_social'] }}</td>
                    <td class="info-label">RUC/Doc:</td>
                    <td>{{ $dispatch['transportista']['num_doc'] }}</td>
                </tr>
            </table>
            @endif

            @if(!empty($dispatch['vehiculo']['placa']))
            <table class="info-section">
                <tr>
                    <td class="info-label">Placa Vehículo:</td>
                    <td>{{ $dispatch['vehiculo']['placa'] }}</td>
                </tr>
            </table>
            @endif

            @if(!empty($dispatch['conductor']))
            <div class="section-title">Conductor(es)</div>
            <table class="info-section">
                @foreach($dispatch['conductor'] as $c)
                <tr>
                    <td class="info-label">{{ $loop->first ? 'Conductor:' : '' }}</td>
                    <td>{{ $c['nombres'] }} {{ $c['apellidos'] }}</td>
                    <td class="info-label">Doc:</td>
                    <td>{{ $c['num_doc'] }}</td>
                </tr>
                @if(!empty($c['licencia']))
                <tr>
                    <td class="info-label">Licencia:</td>
                    <td colspan="3">{{ $c['licencia'] }}</td>
                </tr>
                @endif
                @endforeach
            </table>
            @endif
        </div>
    @endif
@endif
