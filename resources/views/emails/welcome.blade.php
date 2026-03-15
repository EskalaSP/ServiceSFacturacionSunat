@extends('emails.layout')

@section('title', 'Bienvenido')

@section('content')
    <h2>Hola, {{ $tenantName }}</h2>

    <p>Tu cuenta ha sido creada exitosamente. Estás listo para comenzar a emitir comprobantes electrónicos.</p>

    <div class="info-box">
        <p><strong>Plan actual:</strong> {{ ucfirst($plan) }}</p>
        <p><strong>Documentos SUNAT:</strong> Listos para emitir</p>
        <p><strong>Ambiente:</strong> Beta (pruebas)</p>
    </div>

    <p>Para comenzar:</p>
    <p>1. Configura tus series de comprobantes</p>
    <p>2. Registra tus clientes frecuentes</p>
    <p>3. Emite tu primera factura</p>

    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $loginUrl }}" class="btn">Ir a mi cuenta</a>
    </p>

    <hr class="divider">

    <p style="font-size: 13px; color: #94a3b8;">
        Si tienes preguntas, responde a este correo y te ayudaremos.
    </p>
@endsection
