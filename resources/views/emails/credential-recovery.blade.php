@extends('emails.layout')

@section('title', 'Recuperación de credenciales — API SUNAT')

@section('content')

{{-- Greeting --}}
<p style="font-size:22px;font-weight:800;color:#0f172a;margin:0 0 4px;letter-spacing:-0.5px;">{{ $tenantName }}</p>
<p style="font-size:13px;color:#94a3b8;font-weight:600;margin:0 0 32px;">RUC {{ $ruc }}</p>

{{-- Token section --}}
<p style="font-size:10px;font-weight:800;color:#2563eb;letter-spacing:2px;text-transform:uppercase;margin:0 0 10px;">Token de recuperación</p>
<div style="background:#0f172a;border-radius:8px;padding:18px 20px;margin:0 0 12px;">
    <p style="font-family:'Courier New',Courier,monospace;font-size:12px;color:#e2e8f0;word-break:break-all;letter-spacing:1px;line-height:1.8;margin:0;">{{ $token }}</p>
</div>

{{-- Chips --}}
<table cellpadding="0" cellspacing="0" style="margin:0 0 32px;">
    <tr>
        <td style="padding-right:8px;">
            <span style="display:inline-block;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;color:#15803d;">✓ &nbsp;Válido 30 minutos</span>
        </td>
        <td>
            <span style="display:inline-block;background:#fefce8;border:1px solid #fde68a;border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;color:#a16207;">⚡ &nbsp;Un solo uso</span>
        </td>
    </tr>
</table>

{{-- Endpoint section --}}
<p style="font-size:10px;font-weight:800;color:#2563eb;letter-spacing:2px;text-transform:uppercase;margin:0 0 10px;">Endpoint</p>
<table width="100%" cellpadding="0" cellspacing="0" style="border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;">
    <tr>
        <td style="background:#f8fafc;padding:10px 16px;border-bottom:1px solid #e2e8f0;">
            <span style="background:#0f172a;color:#ffffff;font-size:10px;font-weight:800;padding:3px 9px;border-radius:4px;letter-spacing:0.5px;">POST</span>
            &nbsp;&nbsp;
            <span style="font-size:12px;font-weight:700;color:#64748b;">Verificar token</span>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 16px;">
            <span style="font-family:'Courier New',Courier,monospace;font-size:12px;color:#334155;">/api/v1/credenciales/recuperar/verificar</span>
        </td>
    </tr>
</table>

{{-- Divider --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 20px;">
    <tr><td style="height:1px;background:#f1f5f9;font-size:0;line-height:0;">&nbsp;</td></tr>
</table>

{{-- Footer note --}}
<p style="font-size:12px;color:#cbd5e1;margin:0;line-height:1.6;">Si no solicitaste esto, ignora este correo — tus credenciales actuales no se modificarán.</p>

@endsection
