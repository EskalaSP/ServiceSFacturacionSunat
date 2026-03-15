@extends('emails.layout')

@section('title', 'Invitación')

@section('content')
    <h2>{{ $fromBusinessName }} te invita</h2>

    <p>Has recibido una invitación para unirte a {{ config('app.name') }}, la plataforma de facturación electrónica más completa del Perú.</p>

    @if($inviteeRazonSocial)
    <div class="info-box">
        <p><strong>RUC:</strong> {{ $inviteeRuc }}</p>
        <p><strong>Razón Social:</strong> {{ $inviteeRazonSocial }}</p>
    </div>
    @endif

    <p>Al unirte podrás:</p>
    <p>- Emitir facturas, boletas y guías electrónicas</p>
    <p>- Conectarte directamente con {{ $fromBusinessName }}</p>
    <p>- Gestionar tu negocio con herramientas de IA</p>

    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $registerUrl }}" class="btn">Crear mi cuenta gratis</a>
    </p>

    <hr class="divider">

    <p style="font-size: 13px; color: #94a3b8;">
        Esta invitación fue enviada por {{ $fromBusinessName }}. Si no esperabas este correo, puedes ignorarlo.
    </p>
@endsection
