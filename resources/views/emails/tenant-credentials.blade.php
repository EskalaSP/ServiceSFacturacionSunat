<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $appName ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-size:15px;line-height:1.55;">

<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:32px;">

    <p style="margin:0 0 4px;font-size:13px;color:#64748b;letter-spacing:0.4px;text-transform:uppercase;">{{ $appName ?? config('app.name') }}</p>

    <h1 style="margin:0 0 20px;font-size:20px;font-weight:600;color:#0f172a;">
        @if($motivo === 'regeneracion')
            Nuevos accesos generados
        @else
            Bienvenido a {{ $appName ?? config('app.name') }}
        @endif
    </h1>

    <p style="margin:0 0 16px;">
        Hola <strong>{{ $tenantName }}</strong> (RUC {{ $ruc }}).
    </p>

    <p style="margin:0 0 20px;">
        @if($motivo === 'regeneracion')
            Se generaron nuevos accesos para tu integración. Los anteriores dejaron de funcionar en este momento.
        @else
            Ya está lista tu empresa en el sistema. Estos son los datos que necesitas para conectarte desde tu aplicación.
        @endif
    </p>

    <div style="border:1px solid #e2e8f0;border-radius:6px;padding:16px 18px;margin:0 0 12px;background:#f8fafc;">
        <p style="margin:0 0 6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">X-Api-Key</p>
        <p style="margin:0;font-family:Consolas,'Courier New',monospace;font-size:13px;word-break:break-all;color:#0f172a;">{{ $apiKey }}</p>
    </div>

    <div style="border:1px solid #e2e8f0;border-radius:6px;padding:16px 18px;margin:0 0 24px;background:#f8fafc;">
        <p style="margin:0 0 6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">X-Api-Secret</p>
        <p style="margin:0;font-family:Consolas,'Courier New',monospace;font-size:13px;word-break:break-all;color:#0f172a;">{{ $apiSecret }}</p>
    </div>

    <p style="margin:0 0 20px;">
        Envía ambos como <em>headers</em> HTTP en cada request a la API.
    </p>

    <p style="margin:0 0 24px;">
        <a href="{{ $docsUrl }}" style="color:#2563eb;text-decoration:underline;">Ver documentación</a>
    </p>

    <p style="margin:0 0 8px;font-size:13px;color:#64748b;">
        Guarda estos datos en un lugar seguro. El <em>secret</em> no se volverá a mostrar; si lo pierdes tenés que regenerarlo desde el panel.
    </p>

    <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0 16px;">

    <p style="margin:0;font-size:12px;color:#94a3b8;">
        {{ $appName ?? config('app.name') }} — No respondas a este correo.
    </p>

</div>

</body>
</html>
