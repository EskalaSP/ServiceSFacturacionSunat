<style>
    @page { margin: 5mm 4mm; }
    body { font-size: 9pt; line-height: 1.3; color: #000; }

    /* ══════════════════════════════════════
       HEADER — Empresa
       ══════════════════════════════════════ */
    .header-section {
        text-align: center;
        padding-bottom: 6px;
        border-bottom: 2px solid #000;
        margin-bottom: 6px;
    }
    .header-section img {
        max-width: 70px;
        max-height: 70px;
        margin-bottom: 4px;
    }
    .emitter-name {
        font-size: 11pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 2px;
        color: #000;
    }
    .emitter-ruc {
        font-size: 10pt;
        font-weight: bold;
        margin-bottom: 2px;
        color: #000;
    }
    .emitter-address {
        font-size: 8pt;
        color: #000;
        line-height: 1.3;
    }

    /* ══════════════════════════════════════
       BADGE — Tipo de Comprobante
       ══════════════════════════════════════ */
    .document-badge {
        text-align: center;
        border: 2px solid #000;
        padding: 6px 8px;
        margin: 8px 0;
    }
    .badge-title {
        font-size: 10pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #000;
    }
    .badge-number {
        font-size: 12pt;
        font-weight: bold;
        margin: 3px 0 2px;
        letter-spacing: 0.5px;
        color: #000;
    }
    .badge-date {
        font-size: 8.5pt;
        color: #000;
    }

    /* ══════════════════════════════════════
       INFO — Datos del cliente / receptor
       ══════════════════════════════════════ */
    .info-block {
        margin: 6px 0;
        padding: 5px 0;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
    }
    .info-line {
        font-size: 8.5pt;
        margin-bottom: 2px;
        line-height: 1.4;
        color: #000;
    }
    .info-label {
        font-weight: bold;
        color: #000;
    }

    /* ══════════════════════════════════════
       SECTION TITLE
       ══════════════════════════════════════ */
    .section-title-bar {
        font-size: 8.5pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 3px 0;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        margin: 6px 0 4px;
        color: #000;
    }

    /* ══════════════════════════════════════
       ITEMS — Tabla de productos
       ══════════════════════════════════════ */
    .items-table-ticket {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        color: #000;
    }
    .items-table-ticket thead th {
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        padding: 3px 2px;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        color: #000;
        text-align: left;
    }
    .items-table-ticket thead th.col-right {
        text-align: right;
    }
    .items-table-ticket tbody td {
        font-size: 8.5pt;
        padding: 3px 2px;
        border-bottom: 1px dotted #000;
        color: #000;
        vertical-align: top;
    }
    .items-table-ticket tbody td.col-right {
        text-align: right;
        font-weight: bold;
    }
    .items-table-ticket .item-qty-detail {
        font-size: 7.5pt;
        color: #000;
    }

    /* ══════════════════════════════════════
       TOTALS — Tabla de montos
       ══════════════════════════════════════ */
    .totals-section {
        margin-top: 6px;
        padding-top: 4px;
        border-top: 2px solid #000;
    }
    .totals-table-ticket {
        width: 100%;
        border-collapse: collapse;
        color: #000;
    }
    .totals-table-ticket td {
        font-size: 8.5pt;
        padding: 2px 0;
        color: #000;
    }
    .totals-table-ticket .total-label {
        text-align: left;
        color: #000;
    }
    .totals-table-ticket .total-value {
        text-align: right;
        color: #000;
    }
    .totals-table-ticket .total-separator td {
        border-top: 1px solid #000;
        padding-top: 3px;
    }
    .totals-table-ticket .total-final td {
        font-size: 11pt;
        font-weight: bold;
        border-top: 2px solid #000;
        padding-top: 4px;
        color: #000;
    }

    /* ══════════════════════════════════════
       LEYENDA — Monto en letras
       ══════════════════════════════════════ */
    .leyenda {
        font-size: 7.5pt;
        color: #000;
        margin: 6px 0;
        padding: 4px;
        border: 1px solid #000;
        text-align: center;
        font-style: italic;
        line-height: 1.3;
    }

    /* ══════════════════════════════════════
       PAYMENT — Forma de pago / Cuotas
       ══════════════════════════════════════ */
    .payment-section {
        font-size: 8pt;
        margin: 6px 0;
        padding: 4px 0;
        border-top: 1px solid #000;
        color: #000;
    }
    .payment-title {
        font-weight: bold;
        font-size: 8.5pt;
        margin-bottom: 3px;
        color: #000;
    }
    .payment-table-ticket {
        width: 100%;
        border-collapse: collapse;
        color: #000;
    }
    .payment-table-ticket td {
        font-size: 8pt;
        padding: 1px 0;
        color: #000;
    }
    .payment-table-ticket .pay-label { text-align: left; }
    .payment-table-ticket .pay-value { text-align: right; }

    /* ══════════════════════════════════════
       NOTE REFERENCE — Doc afectado (NC/ND)
       ══════════════════════════════════════ */
    .note-reference {
        font-size: 8pt;
        margin: 6px 0;
        padding: 5px;
        border: 1.5px solid #000;
        color: #000;
    }
    .note-reference .ref-title {
        font-weight: bold;
        font-size: 8.5pt;
        margin-bottom: 2px;
        color: #000;
    }

    /* ══════════════════════════════════════
       DISPATCH — Datos de transporte
       ══════════════════════════════════════ */
    .dispatch-section {
        font-size: 8pt;
        margin: 4px 0;
        color: #000;
    }
    .dispatch-section .section-title {
        font-weight: bold;
        font-size: 8.5pt;
        text-align: center;
        padding: 2px 0;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        margin: 4px 0 3px;
        color: #000;
    }

    /* ══════════════════════════════════════
       QR CODE
       ══════════════════════════════════════ */
    .qr-section {
        text-align: center;
        margin: 8px 0 4px;
    }
    .qr-section img { width: 90px; height: 90px; }

    /* ══════════════════════════════════════
       FOOTER
       ══════════════════════════════════════ */
    .footer-section {
        font-size: 7pt;
        color: #000;
        text-align: center;
        margin-top: 4px;
        padding-top: 4px;
        border-top: 2px solid #000;
        line-height: 1.4;
    }
    .footer-hash {
        font-size: 7pt;
        margin-bottom: 3px;
        word-break: break-all;
        color: #000;
    }
    .footer-legal {
        font-size: 7pt;
        font-style: italic;
        color: #000;
    }
</style>
