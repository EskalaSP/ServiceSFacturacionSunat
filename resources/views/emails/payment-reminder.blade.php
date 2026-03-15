@extends('emails.layout')

@section('title', 'Renovación próxima')

@section('content')
    <h2>Tu suscripción se renueva pronto</h2>

    <p>Hola {{ $tenantName }},</p>

    <p>Te informamos que tu suscripción al plan <strong>{{ $planName }}</strong> se renovará automáticamente.</p>

    <div class="info-box">
        <p><strong>Plan:</strong> {{ $planName }}</p>
        <p><strong>Fecha de renovación:</strong> {{ $renewalDate }}</p>
        <p><strong>Monto:</strong> S/ {{ number_format($amount, 2) }}</p>
    </div>

    <p>Si deseas cambiar de plan o actualizar tu método de pago, puedes hacerlo desde tu panel.</p>

    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $billingUrl }}" class="btn">Gestionar suscripción</a>
    </p>

    <hr class="divider">

    <p style="font-size: 13px; color: #94a3b8;">
        Si no deseas renovar, puedes cancelar tu suscripción antes de la fecha de renovación.
    </p>
@endsection
