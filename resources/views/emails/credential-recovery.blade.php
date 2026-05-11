@extends('emails.layout')

@section('title', 'Recuperación de credenciales API')

@section('content')
    <h2>Recuperación de credenciales API</h2>

    <p>Hola, <strong>{{ $tenantName }}</strong> (RUC: {{ $ruc }})</p>

    <p>Recibimos una solicitud para regenerar las credenciales de acceso a la API. Si no fuiste tú, ignora este correo — tus credenciales actuales siguen siendo válidas.</p>

    <div class="warning-box">
        <p><strong>⚠ Este token expira en 30 minutos y es de un solo uso.</strong></p>
        <p>Al usarlo, tus credenciales anteriores quedarán inválidas de inmediato.</p>
    </div>

    <p>Usa el siguiente token en la llamada a la API:</p>

    <div class="info-box">
        <p><strong>Endpoint:</strong></p>
        <p><code>POST {{ config('app.url') }}/api/v1/credenciales/recuperar/verificar</code></p>
        <p style="margin-top:12px;"><strong>Body (JSON):</strong></p>
        <p><code>{ "token": "{{ $token }}" }</code></p>
    </div>

    <p>La respuesta te devolverá el nuevo <strong>api_key</strong> y <strong>api_secret</strong>. Guárdalos inmediatamente — el <strong>api_secret</strong> no se puede recuperar nuevamente.</p>

    <hr class="divider">

    <p style="font-size:13px; color:#94a3b8;">Si no solicitaste esto, no es necesario que hagas nada. Para mayor seguridad, te recomendamos revisar quién tiene acceso a tu cuenta.</p>
@endsection
