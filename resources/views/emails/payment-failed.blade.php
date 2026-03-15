@extends('emails.layout')

@section('title', 'Problema con tu pago')

@section('content')
    <h2>No pudimos procesar tu pago</h2>

    <p>Hola {{ $tenantName }},</p>

    <p>Hubo un problema al intentar cobrar tu suscripción al plan <strong>{{ $planName }}</strong>.</p>

    @if($reason)
    <div class="warning-box">
        <p><strong>Motivo:</strong> {{ $reason }}</p>
    </div>
    @endif

    <p>Para evitar la interrupción de tu servicio, por favor actualiza tu método de pago lo antes posible.</p>

    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $billingUrl }}" class="btn">Actualizar método de pago</a>
    </p>

    <hr class="divider">

    <p style="font-size: 13px; color: #94a3b8;">
        Si tu pago no se completa en los próximos 7 días, tu cuenta será degradada al plan Free.
    </p>
@endsection
