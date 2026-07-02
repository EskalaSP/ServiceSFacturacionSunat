{{-- Tabla de items para A4/A5 — formato SUNAT --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 42px;">Cant.</th>
            <th style="width: 50px;">U. Med.</th>
            <th>Descripción</th>
            @if($tipo_documento !== '09' && $tipo_documento !== '31')
            <th style="width: 78px; text-align: right;">V. Unitario</th>
            <th style="width: 68px; text-align: right;">Descuento</th>
            <th style="width: 78px; text-align: right;">Importe</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td class="text-center">{{ number_format($item['cantidad'], 2) }}</td>
            <td class="text-center">{{ $item['unidad'] }}</td>
            <td>{{ $item['descripcion'] }}</td>
            @if($tipo_documento !== '09' && $tipo_documento !== '31')
            <td class="text-right">{{ number_format($item['valor_unitario'] ?? 0, 2) }}</td>
            <td class="text-right">
                @if(!empty($item['descuento']) && $item['descuento'] > 0)
                    -{{ number_format($item['descuento'], 2) }}
                @else
                    —
                @endif
            </td>
            <td class="text-right">{{ number_format($item['total_item'], 2) }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
