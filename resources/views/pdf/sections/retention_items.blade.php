@php $tipoLabels = ['01' => 'Factura', '03' => 'Boleta', '12' => 'T.Reg.']; @endphp

@if($is_ticket)
    <table class="items-table-ticket">
        <thead>
            <tr>
                <th>Documento</th>
                <th class="col-right">Retenido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documentos_retenidos as $doc)
            <tr>
                <td>
                    <strong>{{ $tipoLabels[$doc['tipo_doc']] ?? $doc['tipo_doc'] }}: {{ $doc['num_doc'] }}</strong>
                    <br><span class="item-qty-detail">Emit: {{ $doc['fecha_emision'] }} | Total: S/ {{ number_format($doc['imp_total'], 2) }}</span>
                    <br><span class="item-qty-detail">F.Ret: {{ $doc['fecha_retencion'] }} | Pagar: S/ {{ number_format($doc['imp_pagar'], 2) }}</span>
                </td>
                <td class="col-right">S/ {{ number_format($doc['imp_retenido'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
@else
    <table class="items-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>N° Documento</th>
                <th>F. Emisión</th>
                <th>Moneda</th>
                <th>Imp. Total</th>
                <th>F. Retención</th>
                <th>Imp. Retenido</th>
                <th>Imp. a Pagar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documentos_retenidos as $doc)
            <tr>
                <td class="text-center">{{ $tipoLabels[$doc['tipo_doc']] ?? $doc['tipo_doc'] }}</td>
                <td class="text-center">{{ $doc['num_doc'] }}</td>
                <td class="text-center">{{ $doc['fecha_emision'] }}</td>
                <td class="text-center">{{ $doc['moneda'] }}</td>
                <td class="text-right">{{ $doc['moneda'] === 'USD' ? '$' : 'S/' }} {{ number_format($doc['imp_total'], 2) }}</td>
                <td class="text-center">{{ $doc['fecha_retencion'] }}</td>
                <td class="text-right">S/ {{ number_format($doc['imp_retenido'], 2) }}</td>
                <td class="text-right">S/ {{ number_format($doc['imp_pagar'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endif
