@extends('pdf.reports.layout')

@section('content')
    @php
        $campos = ['mto_oper_gravadas', 'mto_oper_exoneradas', 'mto_oper_inafectas', 'mto_oper_gratuitas', 'mto_igv', 'mto_isc', 'mto_icbper', 'mto_imp_venta'];
        $fmt = fn ($n) => number_format($n, 2);
    @endphp
    <style>
        .data-table tr.fila-rechazado td { background: #fee2e2 !important; color: #991b1b; }
        .data-table tr.fila-anulado td { background: #e5e7eb !important; color: #6b7280; text-decoration: line-through; }
        .data-table tr.t-total td { background: #e5e7eb !important; font-weight: bold; }
        .data-table tr.t-rechazado td { background: #fee2e2 !important; color: #991b1b; font-weight: bold; }
        .data-table tr.t-anulado td { background: #e5e7eb !important; color: #6b7280; font-weight: bold; }
    </style>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:3%;">Nro</th>
                <th style="width:6%;">F.Emisión</th>
                <th style="width:6%;">F.Venc.</th>
                <th style="width:5%;">Tipo</th>
                <th style="width:8%;">Serie-Corr.</th>
                <th style="width:3%;">T.Doc</th>
                <th style="width:8%;">RUC/DNI</th>
                <th style="width:11%;">Razón Social</th>
                <th class="text-right" style="width:6%;">B.Gravada</th>
                <th class="text-right" style="width:5%;">B.Exoner.</th>
                <th class="text-right" style="width:5%;">B.Inafect.</th>
                <th class="text-right" style="width:5%;">B.Gratuit.</th>
                <th class="text-right" style="width:5%;">IGV</th>
                <th class="text-right" style="width:4%;">ISC</th>
                <th class="text-right" style="width:4%;">ICBPER</th>
                <th class="text-right" style="width:6%;">Total</th>
                <th style="width:3%;">Mon.</th>
                <th style="width:6%;">Estado</th>
                <th style="width:5%;">Amb.</th>
            </tr>
        </thead>
        <tbody>
            @php $nro = 0; @endphp
            @forelse($documentos as $doc)
                @php $nro++; @endphp
                <tr class="{{ $doc['estado_grupo'] === 'rechazado' ? 'fila-rechazado' : ($doc['estado_grupo'] === 'anulado' ? 'fila-anulado' : '') }}">
                    <td class="text-center">{{ $nro }}</td>
                    <td>{{ $doc['fecha_emision'] }}</td>
                    <td>{{ $doc['fecha_vencimiento'] ?? '-' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($doc['tipo_doc_nombre'], 8, '') }}</td>
                    <td>{{ $doc['numero_completo'] }}</td>
                    <td class="text-center">{{ $doc['client_tipo_doc'] }}</td>
                    <td>{{ $doc['client_num_doc'] }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($doc['client_razon_social'], 25) }}</td>
                    @foreach($campos as $c)
                        <td class="text-right">{{ $fmt($doc[$c]) }}</td>
                    @endforeach
                    <td class="text-center">{{ $doc['tipo_moneda'] }}</td>
                    <td class="text-center">{{ $doc['estado_etiqueta'] }}</td>
                    <td class="text-center">{{ $doc['ambiente_etiqueta'] }}</td>
                </tr>
            @empty
                <tr><td colspan="19" class="text-center">No hay documentos con los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- TOTALES: un bloque por ambiente, nunca mezclados --}}
    @foreach($bloques as $bloque)
        <table class="data-table" style="page-break-inside: avoid;">
            <thead>
                <tr>
                    <th colspan="8" style="text-align:left;">TOTALES {{ mb_strtoupper($bloque['nombre']) }}</th>
                    <th class="text-right">B.Gravada</th>
                    <th class="text-right">B.Exoner.</th>
                    <th class="text-right">B.Inafect.</th>
                    <th class="text-right">B.Gratuit.</th>
                    <th class="text-right">IGV</th>
                    <th class="text-right">ISC</th>
                    <th class="text-right">ICBPER</th>
                    <th class="text-right">Total</th>
                    <th colspan="3" class="text-center">Cant. docs</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bloque['lineas'] as $linea)
                    <tr class="t-{{ $linea['estilo'] }}">
                        <td colspan="8" class="text-right">{{ $linea['etiqueta'] }}</td>
                        @foreach($campos as $c)
                            <td class="text-right">{{ $fmt($linea['totales'][$c]) }}</td>
                        @endforeach
                        <td colspan="3" class="text-center">{{ $linea['totales']['cantidad'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

@endsection
