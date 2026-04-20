<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @include('pdf.styles.base')
    @include('pdf.styles.ticket')
    <style>
        @page { margin: 3mm; }
        body { font-size: 8pt; color: #222; }

        /* Header */
        .header-section { padding-bottom: 4px; }
        .header-section img { max-width: 55px; max-height: 55px; }
        .emitter-name { font-size: 9pt; }
        .emitter-ruc { font-size: 8.5pt; }
        .emitter-address { font-size: 7pt; }

        /* Badge */
        .document-badge { padding: 4px 5px; margin: 6px 0; }
        .badge-title { font-size: 8.5pt; }
        .badge-number { font-size: 10pt; }
        .badge-date { font-size: 7.5pt; }

        /* Info */
        .info-line { font-size: 7.5pt; }
        .info-block { padding: 4px 0; }

        /* Items */
        .items-table-ticket thead th { font-size: 7pt; padding: 2px 1px; }
        .items-table-ticket tbody td { font-size: 8pt; padding: 2px 1px; }
        .items-table-ticket .item-qty-detail { font-size: 7pt; }

        /* Totals */
        .totals-table-ticket td { font-size: 8pt; }
        .totals-table-ticket .total-final td { font-size: 9.5pt; }

        /* Others */
        .leyenda { font-size: 7pt; padding: 3px; }
        .qr-section img { width: 75px; height: 75px; }
        .footer-section { font-size: 6.5pt; }
        .footer-hash { font-size: 6.5pt; }
        .footer-legal { font-size: 6.5pt; }
        .note-reference { font-size: 7.5pt; padding: 4px; }
        .note-reference .ref-title { font-size: 8pt; }
        .payment-section { font-size: 7.5pt; }
        .payment-title { font-size: 8pt; }
        .payment-table-ticket td { font-size: 7.5pt; }
        .dispatch-section { font-size: 7.5pt; }
        .dispatch-section .section-title { font-size: 7.5pt; }
    </style>
</head>
<body>
    @foreach($sections as $section)
        @switch($section)
            @case('header')
                @include('pdf.sections.header')
                @break
            @case('document-badge')
                @include('pdf.sections.document_badge')
                @break
            @case('emitter')
                @include('pdf.sections.emitter')
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
                @include('pdf.sections.items_list')
                @break
            @case('totals')
                @include('pdf.sections.totals_compact')
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
</body>
</html>
