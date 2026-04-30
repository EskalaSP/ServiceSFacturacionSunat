<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @include('pdf.styles.base')
    @include('pdf.styles.a4')
    <style>
        @page { margin: 12mm; }
        .page-border {
            border: 0.5px solid #ccc;
            padding: 16px 20px;
        }
    </style>
</head>
<body>
    <div class="page-border">
    {{-- HEADER: Emisor (izq) | Badge (der) --}}
    <table class="header-table">
        <tr>
            <td class="emitter-cell">
                @if(!empty($logo_base64))
                <img src="{{ $logo_base64 }}" alt="Logo" style="max-height: 48px; max-width: 110px; display: block; margin-bottom: 5px;">
                @endif
                <div class="emitter-name">{{ $emisor['razon_social'] }}</div>
                @if(!empty($emisor['nombre_comercial']) && $emisor['nombre_comercial'] !== $emisor['razon_social'])
                <div class="emitter-comercial">{{ $emisor['nombre_comercial'] }}</div>
                @endif
                <div class="emitter-ruc">RUC: {{ $emisor['ruc'] }}</div>
                @if(!empty($emisor['direccion']))
                <div class="emitter-address">{{ $emisor['direccion'] }}</div>
                @endif
                @if(!empty($emisor['cod_local']) && $emisor['cod_local'] !== '0000')
                <div class="emitter-address">Cod. Local: {{ $emisor['cod_local'] }}</div>
                @endif
                @if(!empty($telefonos))
                <div class="emitter-address">Tel: {{ implode(' | ', $telefonos) }}</div>
                @endif
                @if(!empty($emails))
                <div class="emitter-address">{{ implode(' | ', $emails) }}</div>
                @endif
            </td>
            <td class="badge-cell">
                <div class="badge-title">{{ $titulo }}</div>
                <div class="badge-number">{{ $numero_completo }}</div>
                <div class="badge-ruc">RUC: {{ $emisor['ruc'] }}</div>
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
            @case('bank-accounts')
                @include('pdf.sections.bank_accounts')
                @break
            @case('qr-code')
                @include('pdf.sections.qr_code')
                @break
            @case('retention-info')
                @include('pdf.sections.retention_info')
                @break
            @case('retention-items')
                @include('pdf.sections.retention_items')
                @break
            @case('retention-totals')
                @include('pdf.sections.retention_totals')
                @break
            @case('footer')
                @include('pdf.sections.footer')
                @break
        @endswitch
    @endforeach
    </div>
</body>
</html>
