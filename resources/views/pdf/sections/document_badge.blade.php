{{-- Badge del documento: tipo + número --}}
@if($is_ticket)
    <div class="document-badge">
        <div class="badge-title">{{ $titulo }}</div>
        <div class="badge-number">{{ $numero_completo }}</div>
        <div class="badge-date">
            Fecha: {{ $fecha_emision }}
            @if(!empty($fecha_vencimiento))
                <br>Vencimiento: {{ $fecha_vencimiento }}
            @endif
        </div>
    </div>
@else
    {{-- Renderizado dentro del header-table en layouts A4/A5 --}}
@endif
