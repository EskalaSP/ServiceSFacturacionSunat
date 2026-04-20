<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @include('pdf.styles.base')
    @include('pdf.styles.a4')
    <style>
        @page { margin: 12mm; }
        .page-border {
            border: 0.5px solid #000;
            border-radius: 8px;
            padding: 18px 20px;
        }
        .document-badge {
            border: 0.5px solid #000;
            border-radius: 8px;
            padding: 10px 8px;
        }
    </style>
</head>
<body>
    <div class="page-border">
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
                @if(!empty($telefonos))
                <div class="emitter-address">Tel: {{ implode(' | ', $telefonos) }}</div>
                @endif
                @if(!empty($emails))
                <div class="emitter-address">{{ implode(' | ', $emails) }}</div>
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
