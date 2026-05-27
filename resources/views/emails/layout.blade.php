<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'API SUNAT')</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;-webkit-text-size-adjust:100%;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:48px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">

                {{-- Card --}}
                <tr>
                    <td style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">

                        {{-- Top accent bar --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr><td style="height:4px;background:#0f172a;font-size:0;line-height:0;">&nbsp;</td></tr>
                        </table>

                        {{-- Header --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:28px 36px 24px;border-bottom:1px solid #f1f5f9;">
                                    <span style="font-size:17px;font-weight:800;color:#0f172a;letter-spacing:-0.5px;">API </span><span style="font-size:17px;font-weight:800;color:#2563eb;letter-spacing:-0.5px;">SUNAT</span>
                                </td>
                            </tr>
                        </table>

                        {{-- Body --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:36px 36px 28px;">
                                    @yield('content')
                                </td>
                            </tr>
                        </table>

                        {{-- Footer --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:20px 36px;border-top:1px solid #f1f5f9;text-align:center;">
                                    <span style="font-size:11px;color:#cbd5e1;">© {{ date('Y') }} API SUNAT &nbsp;·&nbsp; No respondas a este correo</span>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
