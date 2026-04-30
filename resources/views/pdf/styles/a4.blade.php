<style>
    body { font-size: 10pt; color: #1a1a1a; font-family: Arial, Helvetica, sans-serif; font-weight: normal; }

    /* ── Header ─────────────────────────────────────────────── */
    .header-table { width: 100%; border-collapse: collapse; border-bottom: 2pt solid #52796A; margin-bottom: 10px; }
    .header-table td { vertical-align: middle; }
    .emitter-cell { padding: 10px 14px 10px 0; border-right: 0.5px solid #ddd; }
    .emitter-name { font-size: 12pt; font-weight: bold; color: #52796A; text-transform: uppercase; margin-bottom: 2px; }
    .emitter-comercial { font-size: 8pt; color: #555; margin-bottom: 2px; }
    .emitter-ruc { font-size: 8.5pt; color: #333; margin-bottom: 2px; }
    .emitter-address { font-size: 8pt; color: #555; }
    .badge-cell { width: 185px; text-align: center; padding: 10px 12px; background: #f5f8f5; vertical-align: middle; }
    .badge-title { font-size: 9pt; font-weight: bold; color: #52796A; text-transform: uppercase; letter-spacing: 0.04em; line-height: 1.3; margin-bottom: 5px; }
    .badge-number { font-size: 13pt; font-weight: bold; color: #212528; font-family: 'Courier New', monospace; margin-bottom: 4px; }
    .badge-ruc { font-size: 8.5pt; color: #555; }

    /* ── Receiver info ───────────────────────────────────────── */
    .info-section { width: 100%; border-collapse: collapse; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 0.5px solid #ddd; font-size: 9pt; }
    .info-section td { padding: 2px 4px; vertical-align: top; }
    .info-label { color: #777; width: 120px; white-space: nowrap; }
    .info-value { font-weight: bold; color: #1a1a1a; }
    .info-value-mono { font-weight: bold; color: #1a1a1a; font-family: 'Courier New', monospace; }

    /* ── Items table ─────────────────────────────────────────── */
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .items-table th { background: #52796A; color: #fff; border-bottom: 0.5px solid #3d5c50; padding: 5px 4px; font-size: 8.5pt; font-weight: bold; text-align: center; }
    .items-table td { border-bottom: 0.5px solid #eee; padding: 4px; font-size: 8.5pt; color: #1a1a1a; }
    .items-table tr:nth-child(even) td { background: #f8fbf8; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-left { text-align: left; }
    .font-mono { font-family: 'Courier New', monospace; }

    /* ── Totals ──────────────────────────────────────────────── */
    .totals-table { width: 260px; float: right; border-collapse: collapse; margin-bottom: 10px; }
    .totals-table td { padding: 3px 6px; font-size: 9pt; }
    .total-label { text-align: right; color: #555; }
    .total-value { text-align: right; width: 100px; font-family: 'Courier New', monospace; }
    .totals-table tr { border-bottom: 0.5px solid #eee; }
    .totals-table .total-final td { background: #52796A; color: #fff; font-weight: bold; font-size: 10pt; border-top: none; padding: 5px 6px; }

    /* ── Leyenda ─────────────────────────────────────────────── */
    .leyenda { clear: both; font-size: 8.5pt; color: #444; padding: 5px 8px; font-style: italic; background: #f7f7f7; margin-bottom: 8px; }

    /* ── Payment section ─────────────────────────────────────── */
    .payment-section { font-size: 8.5pt; margin-bottom: 8px; padding: 6px 0; border-bottom: 0.5px solid #ddd; color: #1a1a1a; }
    .payment-section table { width: 100%; border-collapse: collapse; }
    .payment-section td { padding: 2px 4px; vertical-align: top; }
    .payment-title { font-weight: bold; margin-bottom: 4px; }
    .pay-label { color: #777; width: 100px; }
    .pay-value { font-weight: bold; }
    .payment-table-ticket { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .payment-table-ticket td { padding: 2px 4px; font-size: 8pt; }

    /* ── Note reference ──────────────────────────────────────── */
    .note-reference { font-size: 9pt; margin-bottom: 8px; padding: 6px 10px; border-left: 3px solid #52796A; background: #f5f8f5; color: #1a1a1a; }

    /* ── Dispatch section ────────────────────────────────────── */
    .dispatch-section { font-size: 9pt; margin-bottom: 8px; color: #1a1a1a; }
    .dispatch-section table td { padding: 2px 4px; }
    .dispatch-section .section-title { font-weight: bold; color: #52796A; font-size: 9pt; margin-bottom: 4px; padding-bottom: 2px; border-bottom: 0.5px solid #ddd; }

    /* ── QR ──────────────────────────────────────────────────── */
    .qr-section { margin-top: 8px; display: inline-block; vertical-align: top; }
    .qr-section img { width: 90px; height: 90px; }

    /* ── Footer ──────────────────────────────────────────────── */
    .footer-section { font-size: 7.5pt; color: #555; margin-top: 6px; padding-top: 6px; border-top: 0.5px dashed #ccc; }
    .footer-hash { word-break: break-all; font-size: 6.5pt; color: #888; margin-top: 2px; }
    .footer-legal { color: #888; font-style: italic; }

    /* ── Info block (ticket) ─────────────────────────────────── */
    .info-block { margin-bottom: 6px; font-size: 9pt; }
    .info-line { margin-bottom: 2px; }
</style>
