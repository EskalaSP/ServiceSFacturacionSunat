{{-- Leyenda: monto en letras + leyendas SUNAT --}}
@if(!empty($leyenda) && $tipo_documento !== '09' && $tipo_documento !== '31')
    <div class="leyenda">SON: {{ $leyenda }}</div>
@endif
@if(!empty($leyendas_sunat))
    @foreach($leyendas_sunat as $ley)
        <div class="leyenda">{{ $ley }}</div>
    @endforeach
@endif
