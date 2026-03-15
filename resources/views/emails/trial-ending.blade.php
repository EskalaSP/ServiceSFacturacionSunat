@extends('emails.layout')

@section('title', 'Tu prueba gratis termina pronto')

@section('content')
    <h2>Tu prueba gratis termina en 3 días</h2>

    <p>Hola {{ $tenantName }},</p>

    <p>Tu período de prueba del plan <strong>{{ $planName }}</strong> finaliza el <strong>{{ $trialEndsAt }}</strong>.</p>

    <div class="info-box">
        <p><strong>¿Qué pasa después?</strong></p>
        <p>Si no eliges un plan, tu cuenta será degradada al plan Free con funcionalidades limitadas.</p>
    </div>

    <p>Para mantener acceso a todas las funcionalidades:</p>
    <p>- Documentos SUNAT ilimitados</p>
    <p>- Copiloto IA avanzado</p>
    <p>- CRM y gestión de citas</p>
    <p>- Múltiples sucursales y usuarios</p>

    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $billingUrl }}" class="btn">Elegir mi plan</a>
    </p>

    <hr class="divider">

    <p style="font-size: 13px; color: #94a3b8;">
        Planes desde S/ 99/mes. Cancela cuando quieras.
    </p>
@endsection
