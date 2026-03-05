<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    @include('pdf.styles.base')
    @include('pdf.styles.ticket')
    <style>
        body { font-size: 7pt; }
        .emitter-name { font-size: 8pt; }
        .emitter-ruc { font-size: 7pt; }
        .emitter-address { font-size: 6.5pt; }
        .badge-title { font-size: 7pt; }
        .badge-number { font-size: 8pt; }
        .info-line { font-size: 7pt; }
        .item-line { font-size: 7pt; }
        .total-line { font-size: 7pt; }
        .total-final { font-size: 7.5pt; }
        .leyenda { font-size: 6.5pt; }
        .qr-section img { width: 70px; height: 70px; }
        .footer-section { font-size: 6pt; }
        .header-section img { max-width: 50px; max-height: 50px; }
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
