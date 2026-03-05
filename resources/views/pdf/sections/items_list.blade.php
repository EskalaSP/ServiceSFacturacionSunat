{{-- Lista compacta de items para tickets --}}
<div class="separator-dashed"></div>
@foreach($items as $item)
<div class="item-line">
    <span class="item-desc">{{ $item['cantidad'] }} x {{ $item['descripcion'] }}</span>
    @if($tipo_documento !== '09')
    <span class="item-amount">{{ number_format($item['total_item'], 2) }}</span>
    @endif
</div>
@endforeach
<div class="separator-dashed"></div>
