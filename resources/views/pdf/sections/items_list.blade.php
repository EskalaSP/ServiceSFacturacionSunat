{{-- Lista de items para tickets (tabla) --}}
<table class="items-table-ticket">
    <thead>
        <tr>
            <th>Descripcion</th>
            @if($tipo_documento !== '09')
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
                <span class="item-qty-detail">{{ number_format($item['cantidad'], 2) }} {{ $item['unidad'] ?? 'NIU' }} x {{ number_format($item['precio_unitario'], 2) }}</span>
                @if(!empty($item['descuento']) && $item['descuento'] > 0)
                <br><span class="item-qty-detail">Dscto: -{{ number_format($item['descuento'], 2) }}</span>
                @endif
            </td>
            @if($tipo_documento !== '09')
            <td class="col-right">{{ number_format($item['total_item'], 2) }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
