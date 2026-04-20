<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @include('pdf.styles.base')
    @include('pdf.styles.a4')
    <style>
        @page { margin: 8mm; }
        body { font-size: 8.5pt; }
        .page-border {
            border: 0.5px solid #000;
            border-radius: 6px;
            padding: 12px 14px;
        }
        .document-badge {
            border: 0.5px solid #000;
            border-radius: 6px;
            padding: 7px 6px;
        }
        .emitter-name { font-size: 10pt; }
        .badge-title { font-size: 8.5pt; }
        .badge-number { font-size: 9.5pt; }
        .badge-cell { width: 170px; }
        .items-table th { font-size: 7pt; padding: 3px; }
        .items-table td { font-size: 7.5pt; padding: 3px; }
        .totals-table { width: 240px; }
        .totals-table td { font-size: 8pt; }
        .info-section { font-size: 8pt; }
        .leyenda { font-size: 7.5pt; }
        .footer-section { font-size: 7pt; }
        .qr-section img { width: 85px; height: 85px; }
        .logo-cell img { max-width: 55px; max-height: 55px; }
    </style>
</head>
<body>
    <div class="page-border">
    {{-- HEADER --}}
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

    @foreach($sections as $section)
        @switch($section)
            @case('header')
            @case('document-badge')
            @case('emitter')
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
