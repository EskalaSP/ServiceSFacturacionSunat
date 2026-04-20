<table class="items-table" style="width:100%; border-collapse:collapse; margin: 8px 0;">
    <thead>
        <tr>
            <th style="text-align:center; border:0.5px solid #666; background:#f0f0f0; padding:4px;">Tipo Doc.</th>
            <th style="text-align:center; border:0.5px solid #666; background:#f0f0f0; padding:4px;">N° Documento</th>
            <th style="text-align:center; border:0.5px solid #666; background:#f0f0f0; padding:4px;">Fecha Emisión</th>
            <th style="text-align:right;  border:0.5px solid #666; background:#f0f0f0; padding:4px;">Importe Total</th>
            <th style="text-align:center; border:0.5px solid #666; background:#f0f0f0; padding:4px;">F. Retención</th>
            <th style="text-align:right;  border:0.5px solid #666; background:#f0f0f0; padding:4px;">Imp. Retenido</th>
            <th style="text-align:right;  border:0.5px solid #666; background:#f0f0f0; padding:4px;">Imp. a Pagar</th>
        </tr>
    </thead>
    <tbody>
        @php
            $tipoLabels = ['01' => 'Factura', '03' => 'Boleta', '12' => 'T. Registro'];
        @endphp
        @foreach($documentos_retenidos as $doc)
        <tr>
            <td style="text-align:center; border:0.5px solid #ccc; padding:4px;">
                {{ $tipoLabels[$doc['tipo_doc']] ?? $doc['tipo_doc'] }}
            </td>
            <td style="text-align:center; border:0.5px solid #ccc; padding:4px;">{{ $doc['num_doc'] }}</td>
            <td style="text-align:center; border:0.5px solid #ccc; padding:4px;">{{ $doc['fecha_emision'] }}</td>
            <td style="text-align:right;  border:0.5px solid #ccc; padding:4px;">
                {{ $doc['moneda'] === 'USD' ? '$' : 'S/' }} {{ number_format($doc['imp_total'], 2) }}
            </td>
            <td style="text-align:center; border:0.5px solid #ccc; padding:4px;">{{ $doc['fecha_retencion'] }}</td>
            <td style="text-align:right;  border:0.5px solid #ccc; padding:4px;">
                S/ {{ number_format($doc['imp_retenido'], 2) }}
            </td>
            <td style="text-align:right;  border:0.5px solid #ccc; padding:4px;">
                S/ {{ number_format($doc['imp_pagar'], 2) }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
