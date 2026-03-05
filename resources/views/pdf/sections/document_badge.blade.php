{{-- Badge del documento: tipo + número --}}
@if($is_ticket)
    <div class="document-badge">
        <div class="separator-dashed"></div>
        <div class="badge-title">{{ $titulo }}</div>
        <div class="badge-number">{{ $numero_completo }}</div>
        <div style="font-size: 7.5pt;">{{ $fecha_emision }}</div>
        <div class="separator-dashed"></div>
    </div>
@else
    {{-- Renderizado dentro del header-table en layouts A4/A5 --}}
@endif
