Hola {{ $tenantName }} (RUC {{ $ruc }}),

@if($motivo === 'regeneracion')
Se generaron nuevos accesos para tu integracion con nuestra API. Los anteriores dejaron de funcionar.
@else
Ya esta lista tu empresa en el sistema. Te damos la bienvenida.
@endif

Adjuntamos en este correo un archivo de texto (.txt) con los datos de acceso que necesitas para conectarte desde tu aplicacion.

Abrelo, revisa los datos y guardalo en un lugar seguro.

Documentacion: {{ $docsUrl }}

—
{{ $appName ?? config('app.name') }}
