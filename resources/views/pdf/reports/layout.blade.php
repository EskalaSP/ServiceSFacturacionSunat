<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #000;
            line-height: 1.4;
            margin: 20px 25px;
        }

        /* Header */
        .header-table { width: 100%; margin-bottom: 10px; border-bottom: 0.5px solid #000; padding-bottom: 6px; }
        .header-table td { vertical-align: top; }
        .logo { max-height: 45px; max-width: 110px; }
        .empresa-name { font-size: 13px; font-weight: bold; color: #000; }
        .empresa-info { font-size: 9px; color: #444; }
        .report-title { font-size: 14px; font-weight: bold; color: #000; text-align: center; margin: 6px 0 3px; }
        .report-period { font-size: 9.5px; text-align: center; color: #444; margin-bottom: 6px; }
        .filters-applied { font-size: 8.5px; color: #666; text-align: center; margin-bottom: 8px; }

        /* KPI boxes */
        .kpi-container { width: 100%; margin-bottom: 8px; }
        .kpi-container td { width: 25%; padding: 2px 3px; }
        .kpi-box { border: 0.5px solid #000; border-radius: 4px; padding: 5px 6px; text-align: center; }
        .kpi-value { font-size: 13px; font-weight: bold; color: #000; }
        .kpi-label { font-size: 7px; color: #555; text-transform: uppercase; margin-top: 1px; letter-spacing: 0.3px; }
        .kpi-highlight .kpi-box,
        .kpi-warning .kpi-box,
        .kpi-danger .kpi-box { border-color: #000; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8.5px; }
        .data-table th { color: #000; padding: 3px; text-align: left; font-weight: bold; font-size: 8px; border-bottom: 0.5px solid #000; }
        .data-table td { padding: 3px; border-bottom: 0.5px solid #000; color: #000; }
        .data-table tr:nth-child(even) td { background: #f5f5f5; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }
        .data-table .subtotal td { font-weight: bold; background: #f0f0f0 !important; border-top: 0.5px solid #000; }
        .data-table .total td { font-weight: bold; background: #eee !important; color: #000; border-top: 0.5px solid #000; font-size: 9.5px; }

        .section-title { font-size: 10px; font-weight: bold; color: #000; margin: 10px 0 4px; padding-bottom: 2px; border-bottom: 0.5px solid #000; }

        /* Aging bars */
        .aging-bar { height: 10px; border-radius: 2px; display: inline-block; min-width: 2px; }
        .aging-green { background: #888; }
        .aging-yellow { background: #999; }
        .aging-orange { background: #777; }
        .aging-red { background: #555; }
        .aging-darkred { background: #333; }
        .aging-crimson { background: #111; }

        /* Footer */
        .footer { font-size: 7px; color: #888; padding: 4px 0; margin-top: 10px; border-top: 0.5px solid #000; }
        .footer-left { float: left; }
        .footer-right { float: right; }

        .page-break { page-break-after: always; }
        .no-break { page-break-inside: avoid; }

        .badge { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 7px; font-weight: bold; border: 0.5px solid #000; color: #000; }
    </style>
    @yield('styles')
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 15%;">
                @if(!empty($tenant['logo_base64']))
                    <img src="{{ $tenant['logo_base64'] }}" class="logo">
                @endif
            </td>
            <td style="width: 55%;">
                <div class="empresa-name">{{ $tenant['nombre_comercial'] }}</div>
                <div class="empresa-info">RUC: {{ $tenant['ruc'] }}</div>
                <div class="empresa-info">{{ $tenant['direccion'] ?? '' }}</div>
            </td>
            <td style="width: 30%; text-align: right;">
                <div style="font-size: 8px; color: #888;">Generado: {{ $generated_at }}</div>
            </td>
        </tr>
    </table>

    <div class="report-title">{{ $titulo }}</div>
    <div class="report-period">
        Periodo: {{ \Carbon\Carbon::parse($periodo['desde'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($periodo['hasta'])->format('d/m/Y') }}
    </div>

    @if(!empty($filtros) && count($filtros) > 0)
        <div class="filters-applied">
            Filtros:
            @foreach($filtros as $f)
                {{ $f['filtro'] }}: {{ $f['valor'] }}@if(!$loop->last) | @endif
            @endforeach
        </div>
    @endif

    @yield('content')

    {{-- Footer --}}
    <div class="footer">
        <span class="footer-left">{{ $tenant['nombre_comercial'] }} — {{ $titulo }}</span>
        <span class="footer-right">Generado por Platform | {{ $generated_at }}</span>
    </div>
</body>
</html>
