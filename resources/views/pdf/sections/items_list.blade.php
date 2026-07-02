{{-- Lista de items para tickets (tabla) --}}
<table class="items-table-ticket">
    <thead>
        <tr>
            <th>Descripcion</th>
            @if($tipo_documento !== '09' && $tipo_documento !== '31')
            <th class="col-right">Importe</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td>
                <strong>{{ $item['descripcion'] }}</strong>
                <br>
                <span class="item-qty-detail">
                    {{ number_format($item['cantidad'], 2) }} {{ $item['unidad'] ?? 'NIU' }}
                    @if($tipo_documento !== '09' && $tipo_documento !== '31')
                        x {{ number_format($item['precio_unitario'], 2) }}
                    @endif
                </span>
                @if(!empty($item['descuento']) && $item['descuento'] > 0)
                <br><span style="color: #c0392b; font-weight: bold; font-size: 0.8em;">Dscto: -{{ number_format($item['descuento'], 2) }}</span>
                @endif
            </td>
            @if($tipo_documento !== '09' && $tipo_documento !== '31')
            <td class="col-right">{{ number_format($item['total_item'], 2) }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
