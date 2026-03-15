@extends('emails.layout')

@section('title', 'Solicitud de conexión')

@section('content')
    <h2>Nueva solicitud de conexión</h2>

    <p>Hola {{ $toBusinessName }},</p>

    <p><strong>{{ $fromBusinessName }}</strong> quiere conectar contigo en {{ config('app.name') }}.</p>

    <div class="info-box">
        <p>Al aceptar la conexión podrán:</p>
        <p>- Facturar entre sí de forma directa</p>
        <p>- Compartir catálogos de productos</p>
        <p>- Automatizar procesos comerciales</p>
    </div>

    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $actionUrl }}" class="btn">Ver solicitud</a>
    </p>
@endsection
