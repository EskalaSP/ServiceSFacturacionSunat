<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @include('pdf.styles.base')
    @include('pdf.styles.a4')
</head>
<body>
    {{-- HEADER: Logo + Emisor + Badge --}}
    <table class="header-table">
        <tr>
            @if(!empty($logo_base64))
            <td class="logo-cell">
                <img src="{{ $logo_base64 }}" alt="Logo">
            </td>
            @endif
            <td class="emitter-cell">
                <div class="emitter-name">{{ $emisor['nombre_comercial'] }}</div>
                <div class="emitter-ruc">RUC: {{ $emisor['ruc'] }}</div>
                <div class="emitter-address">{{ $emisor['direccion'] }}</div>
                @if($emisor['cod_local'] !== '0000')
                <div class="emitter-address">Cod. Local: {{ $emisor['cod_local'] }}</div>
                @endif
            </td>
            <td class="badge-cell">
                <div class="document-badge">
                    <div class="badge-ruc">RUC: {{ $emisor['ruc'] }}</div>
                    <div class="badge-title">{{ $titulo }}</div>
                    <div class="badge-number">{{ $numero_completo }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Secciones dinámicas --}}
    @foreach($sections as $section)
        @switch($section)
            @case('header')
            @case('document-badge')
            @case('emitter')
                {{-- Ya renderizados arriba --}}
                @break
            @case('receiver')
                @include('pdf.sections.receiver')
                @break
            @case('note-reference')
                @include('pdf.sections.note_reference')
                @break
            @case('dispatch-info')
                @include('pdf.sections.dispatch_info')
                @break
            @case('items')
                @include('pdf.sections.items_table')
                @break
            @case('totals')
                @include('pdf.sections.totals')
                @break
            @case('leyenda')
                @include('pdf.sections.leyenda')
                @break
            @case('payment-info')
                @include('pdf.sections.payment_info')
                @break
            @case('qr-code')
                @include('pdf.sections.qr_code')
                @break
            @case('footer')
                @include('pdf.sections.footer')
                @break
        @endswitch
    @endforeach
</body>
</html>
