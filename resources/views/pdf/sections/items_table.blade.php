{{-- Tabla de items para A4/A5 --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 50px;">Código</th>
            <th>Descripción</th>
            <th style="width: 35px;">Und</th>
            <th style="width: 40px;">Cant.</th>
            <th style="width: 65px;">P. Unit.</th>
            @if($tipo_documento !== '09')
            <th style="width: 55px;">IGV</th>
            <th style="width: 70px;">Importe</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td class="text-center">{{ $item['codigo'] }}</td>
            <td>{{ $item['descripcion'] }}</td>
            <td class="text-center">{{ $item['unidad'] }}</td>
            <td class="text-center">{{ number_format($item['cantidad'], 2) }}</td>
            <td class="text-right">{{ number_format($item['precio_unitario'], 2) }}</td>
            @if($tipo_documento !== '09')
            <td class="text-right">{{ number_format($item['igv'], 2) }}</td>
            <td class="text-right">{{ number_format($item['total_item'], 2) }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>
