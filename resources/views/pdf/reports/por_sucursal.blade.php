@extends('pdf.reports.layout')

@section('content')
    {{-- Tabla Comparativa --}}
    <div class="section-title">Comparativo por Sucursal</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Sucursal</th>
                <th>Cod.Local</th>
                <th class="text-right">Fact. (Cant)</th>
                <th class="text-right">Fact. (S/)</th>
                <th class="text-right">Bol. (Cant)</th>
                <th class="text-right">Bol. (S/)</th>
                <th class="text-right">NC (Cant)</th>
                <th class="text-right">NC (S/)</th>
                <th class="text-right">ND (Cant)</th>
                <th class="text-right">ND (S/)</th>
                <th class="text-right">Bruto</th>
                <th class="text-right">Neto</th>
                <th class="text-right">IGV</th>
                <th class="text-right">Cobrado</th>
                <th class="text-right">Pend.</th>
                <th class="text-right">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comparativo as $s)
            <tr>
                <td>{{ \Illuminate\Support\Str::limit($s['nombre'], 15) }}</td>
                <td class="text-center">{{ $s['cod_local'] }}</td>
                <td class="text-right">{{ $s['facturas']['cantidad'] }}</td>
                <td class="text-right">{{ number_format($s['facturas']['monto'], 2) }}</td>
                <td class="text-right">{{ $s['boletas']['cantidad'] }}</td>
                <td class="text-right">{{ number_format($s['boletas']['monto'], 2) }}</td>
                <td class="text-right">{{ $s['notas_credito']['cantidad'] }}</td>
                <td class="text-right">{{ number_format($s['notas_credito']['monto'], 2) }}</td>
                <td class="text-right">{{ $s['notas_debito']['cantidad'] }}</td>
                <td class="text-right">{{ number_format($s['notas_debito']['monto'], 2) }}</td>
                <td class="text-right">{{ number_format($s['total_bruto'], 2) }}</td>
                <td class="text-right">{{ number_format($s['neto'], 2) }}</td>
                <td class="text-right">{{ number_format($s['igv'], 2) }}</td>
                <td class="text-right">{{ number_format($s['cobrado'], 2) }}</td>
                <td class="text-right">{{ number_format($s['pendiente'], 2) }}</td>
                <td class="text-right">{{ $s['porcentaje_total'] }}%</td>
            </tr>
            @endforeach

            {{-- Total --}}
            <tr class="total">
                <td colspan="2" class="text-right">TOTAL:</td>
                <td class="text-right">{{ $comparativo->sum('facturas.cantidad') }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('facturas.monto'), 2) }}</td>
                <td class="text-right">{{ $comparativo->sum('boletas.cantidad') }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('boletas.monto'), 2) }}</td>
                <td class="text-right">{{ $comparativo->sum('notas_credito.cantidad') }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('notas_credito.monto'), 2) }}</td>
                <td class="text-right">{{ $comparativo->sum('notas_debito.cantidad') }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('notas_debito.monto'), 2) }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('total_bruto'), 2) }}</td>
                <td class="text-right">{{ number_format($gran_total, 2) }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('igv'), 2) }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('cobrado'), 2) }}</td>
                <td class="text-right">{{ number_format($comparativo->sum('pendiente'), 2) }}</td>
                <td class="text-right">100%</td>
            </tr>
        </tbody>
    </table>

    {{-- KPIs por sucursal --}}
    <div class="section-title">KPIs por Sucursal</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Sucursal</th>
                <th class="text-right">Ticket Promedio (S/)</th>
                <th class="text-right">Docs/Día Promedio</th>
                <th class="text-right">% del Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comparativo as $s)
            <tr>
                <td>{{ $s['nombre'] }}</td>
                <td class="text-right">{{ number_format($s['ticket_promedio'], 2) }}</td>
                <td class="text-right">{{ $s['docs_dia_promedio'] }}</td>
                <td class="text-right">
                    {{ $s['porcentaje_total'] }}%
                    <div class="aging-bar aging-green" style="width: {{ $s['porcentaje_total'] }}%; display: inline-block; vertical-align: middle;"></div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection
