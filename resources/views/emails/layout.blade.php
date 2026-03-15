<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f6f8;
            color: #1a1a2e;
            line-height: 1.6;
            -webkit-text-size-adjust: 100%;
        }
        .email-wrapper {
            width: 100%;
            padding: 32px 16px;
            background-color: #f4f6f8;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        .email-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            padding: 28px 32px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .email-body {
            padding: 32px;
        }
        .email-body h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1a1a2e;
        }
        .email-body p {
            margin-bottom: 16px;
            color: #4a5568;
            font-size: 15px;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            text-align: center;
            margin: 8px 0;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1a1a2e !important;
        }
        .info-box {
            background-color: #f0f4ff;
            border-left: 4px solid #2563eb;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }
        .info-box p {
            margin-bottom: 4px;
            color: #1e40af;
            font-size: 14px;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }
        .warning-box p {
            margin-bottom: 4px;
            color: #92400e;
            font-size: 14px;
        }
        .email-footer {
            padding: 24px 32px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        .email-footer p {
            color: #94a3b8;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .email-footer a {
            color: #2563eb;
            text-decoration: none;
        }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 24px 0;
        }
        @media (max-width: 600px) {
            .email-body { padding: 24px 20px; }
            .email-header { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <div class="email-container">
        <div class="email-header">
            <h1>{{ config('app.name', 'Platform') }}</h1>
        </div>
        <div class="email-body">
            @yield('content')
        </div>
        <div class="email-footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Platform') }}. Todos los derechos reservados.</p>
            <p><a href="{{ config('app.url') }}">{{ config('app.url') }}</a></p>
        </div>
    </div>
</div>
</body>
</html>
