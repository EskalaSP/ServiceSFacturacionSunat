<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1a1a1a;
            line-height: 1.4;
            font-size: 9pt;
            margin: 30px 28px;
        }
        @page { margin: 10mm; }
        table { width: 100%; border-collapse: collapse; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* ── Page container ── */
        .page-border {
            padding: 10px 10px;
        }

        /* ── Header ── */
        .header-table { margin-bottom: 6px; }
        .header-table td { vertical-align: middle; }
        .logo-cell { width: 90px; }
        .logo-cell img { max-width: 80px; max-height: 80px; }
        .emitter-cell { padding-left: 10px; }
        .empresa-name { font-size: 12pt; font-weight: bold; color: #000; }
        .empresa-info { font-size: 8pt; font-weight: bold; color: #000; }

        /* ── Title ── */
        .report-title-section {
            text-align: center;
            padding: 8px 0;
            margin-bottom: 8px;
            border-top: 0.5px solid #000;
            border-bottom: 0.5px solid #000;
        }
        .report-title { font-size: 12pt; font-weight: bold; color: #000; letter-spacing: 0.5px; }
        .report-period { font-size: 9pt; font-weight: bold; color: #000; margin-top: 2px; }
        .filters-applied { font-size: 8pt; color: #444; margin-top: 2px; }

        /* ── KPI boxes ── */
        .kpi-container { width: 100%; margin-bottom: 12px; }
        .kpi-container td { padding: 2px 3px; }
        .kpi-box {
            border: 0.5px solid #000;
            border-radius: 6px;
            padding: 7px 6px;
            text-align: center;
        }
        .kpi-value { font-size: 13px; font-weight: bold; color: #000; }
        .kpi-label { font-size: 6.5px; color: #000; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.4px; }
        .kpi-highlight .kpi-value { color: #166534; }
        .kpi-warning .kpi-value { color: #92400e; }
        .kpi-danger .kpi-value { color: #991b1b; }

        /* ── Data tables ── */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8.5pt; }
        .data-table th {
            background: #f5f5f5;
            border-bottom: 0.5px solid #000;
            padding: 5px 4px;
            font-size: 8pt;
            font-weight: bold;
            text-align: left;
            color: #000;
        }
        .data-table td {
            border-bottom: 0.5px solid #000;
            padding: 4px;
            font-weight: bold;
            color: #000;
        }
        .data-table tr:nth-child(even) td { background: #fafafa; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }
        .data-table .subtotal td { font-weight: bold; background: #f0f0f0 !important; border-top: 0.5px solid #000; }
        .data-table .total td { font-weight: bold; background: #eee !important; color: #000; border-top: 0.5px solid #000; font-size: 9.5pt; }

        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #000;
            margin: 12px 0 4px;
            padding-bottom: 3px;
            border-bottom: 0.5px solid #000;
        }

        /* ── Aging bars ── */
        .aging-bar { height: 10px; border-radius: 2px; display: inline-block; min-width: 2px; }
        .aging-green { background: #16a34a; }
        .aging-yellow { background: #d97706; }
        .aging-orange { background: #ea580c; }
        .aging-red { background: #dc2626; }
        .aging-darkred { background: #991b1b; }
        .aging-crimson { background: #450a0a; }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }

        /* ── Footer ── */
        .footer {
            font-size: 7.5pt;
            font-weight: bold;
            color: #000;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 0.5px solid #000;
        }

        .page-break { page-break-after: always; }
        .no-break { page-break-inside: avoid; }
    </style>
    @yield('styles')
</head>
<body>
    <div class="page-border">

    {{-- ══ HEADER ══ --}}
    <table class="header-table">
        <tr>
            @if(!empty($tenant['logo_base64']))
            <td class="logo-cell">
                <img src="{{ $tenant['logo_base64'] }}" alt="Logo">
            </td>
            @endif
            <td class="emitter-cell">
                <div class="empresa-name">{{ $tenant['nombre_comercial'] }}</div>
                <div class="empresa-info">RUC: {{ $tenant['ruc'] }}</div>
                <div class="empresa-info">{{ $tenant['direccion'] ?? '' }}</div>
            </td>
            <td style="text-align:right; font-size:8pt; color:#555; width:130px;">
                {{ $generated_at }}
            </td>
        </tr>
    </table>

    {{-- ══ TITLE ══ --}}
    <div class="report-title-section">
        <div class="report-title">{{ $titulo }}</div>
        @if(isset($periodo) && $periodo['desde'] !== '-')
        <div class="report-period">
            {{ \Carbon\Carbon::parse($periodo['desde'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($periodo['hasta'])->format('d/m/Y') }}
        </div>
        @endif
        @if(!empty($filtros) && count($filtros) > 0)
        <div class="filters-applied">
            Filtros:
            @foreach($filtros as $f)
                {{ $f['filtro'] }}: {{ $f['valor'] }}@if(!$loop->last) | @endif
            @endforeach
        </div>
        @endif
    </div>

    @yield('content')

    {{-- ══ FOOTER ══ --}}
    <div class="footer">
        <table>
            <tr>
                <td style="width:50%;">Generado el {{ $generated_at }}</td>
                <td style="text-align:right;">{{ $tenant['nombre_comercial'] }} — {{ $titulo }}</td>
            </tr>
        </table>
    </div>

    </div>
</body>
</html>
