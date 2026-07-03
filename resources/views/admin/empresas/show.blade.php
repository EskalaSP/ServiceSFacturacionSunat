@extends('admin.layouts.app')

@section('title', $tenant->razon_social)
@section('heading', $tenant->razon_social)
@section('heading_actions')
    <div class="inline-flex gap-2">
        <a href="{{ route('admin.empresas.edit', $tenant) }}"
           class="px-3 py-1.5 bg-gray-800 text-white rounded text-sm hover:bg-gray-900">Editar</a>
        <form method="POST" action="{{ route('admin.empresas.regenerar', $tenant) }}"
              onsubmit="return confirm('¿Regenerar api_key y api_secret? Las credenciales anteriores dejarán de funcionar inmediatamente.');" class="inline">
            @csrf
            <button type="submit" class="px-3 py-1.5 bg-amber-500 text-white rounded text-sm hover:bg-amber-600">Regenerar credenciales</button>
        </form>
        <form method="POST" action="{{ route('admin.empresas.toggle', $tenant) }}" class="inline">
            @csrf
            <button type="submit"
                    class="px-3 py-1.5 {{ $tenant->is_active ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }} rounded text-sm">
                {{ $tenant->is_active ? 'Desactivar' : 'Activar' }}
            </button>
        </form>
    </div>
@endsection

@section('content')

{{-- Modal de credenciales — solo visible tras crear o regenerar --}}
@if ($credencialesNuevas)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" x-data="{ open: true }" x-show="open" x-cloak>
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">🔑 Credenciales de la API</h2>
                <p class="text-sm text-gray-500">Copia estas credenciales ahora. Son las únicas que debes darle al cliente.</p>
            </div>
            <button @click="open = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded p-3 mb-4 text-sm text-amber-800">
            ⚠️ El <strong>api_secret</strong> no se volverá a mostrar. Si lo pierdes, regenera credenciales.
        </div>

        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase mb-1">Empresa</label>
                <div class="text-sm">{{ $credencialesNuevas['ruc'] }} — {{ $credencialesNuevas['razon_social'] }}</div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase mb-1">X-Api-Key</label>
                <div class="flex gap-2">
                    <input type="text" readonly value="{{ $credencialesNuevas['api_key'] }}" id="cred-key"
                           class="flex-1 font-mono text-xs bg-gray-50 rounded border-gray-300">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cred-key').value); this.innerText='Copiado ✓'"
                            class="px-3 py-1.5 bg-sky-600 text-white rounded text-xs hover:bg-sky-700">Copiar</button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase mb-1">X-Api-Secret</label>
                <div class="flex gap-2">
                    <input type="text" readonly value="{{ $credencialesNuevas['api_secret'] }}" id="cred-secret"
                           class="flex-1 font-mono text-xs bg-gray-50 rounded border-gray-300">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cred-secret').value); this.innerText='Copiado ✓'"
                            class="px-3 py-1.5 bg-sky-600 text-white rounded text-xs hover:bg-sky-700">Copiar</button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase mb-1">Ejemplo curl</label>
                <pre class="bg-gray-900 text-gray-100 text-xs rounded p-3 overflow-x-auto">curl {{ url('/api/v1/empresa') }} \
  -H "X-Api-Key: {{ $credencialesNuevas['api_key'] }}" \
  -H "X-Api-Secret: •••••••••" \
  -H "Accept: application/json"</pre>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button @click="open = false" class="px-4 py-2 bg-gray-800 text-white rounded text-sm hover:bg-gray-900">
                Ya guardé las credenciales
            </button>
        </div>
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Datos principales --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Identidad</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500 text-xs uppercase">RUC</dt><dd class="font-mono text-gray-900">{{ $tenant->ruc }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Razón social</dt><dd class="text-gray-900">{{ $tenant->razon_social }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Nombre comercial</dt><dd class="text-gray-900">{{ $tenant->nombre_comercial ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Dirección</dt><dd class="text-gray-900">{{ $tenant->direccion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Ubigeo</dt><dd class="font-mono text-gray-900">{{ $tenant->ubigeo ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Departamento / Provincia / Distrito</dt><dd class="text-gray-900">{{ implode(' / ', array_filter([$tenant->departamento, $tenant->provincia, $tenant->distrito])) ?: '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Teléfonos</dt><dd class="text-gray-900">{{ implode(', ', $tenant->telefonos ?? []) ?: '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Emails</dt><dd class="text-gray-900">{{ implode(', ', $tenant->emails ?? []) ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">SUNAT & régimen</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500 text-xs uppercase">Entorno</dt><dd>
                    <span class="uppercase font-medium {{ $tenant->environment === 'production' ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $tenant->environment }}
                    </span>
                </dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Régimen tributario</dt><dd class="text-gray-900">{{ $tenant->tax_regime }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Certificado</dt><dd class="text-gray-900">{{ $tenant->certificate_path ? '✔ Cargado' : '— No cargado' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">SIRE</dt><dd class="text-gray-900">{{ $tenant->sire_enabled ? '✔ Activo' : 'Inactivo' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Webhook</dt><dd class="text-gray-900 truncate">{{ $tenant->webhook_url ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 text-xs uppercase">Plan / Límite</dt><dd class="text-gray-900 uppercase">{{ $tenant->plan }} — {{ $tenant->max_documents_month ?? 0 }} docs/mes</dd></div>
            </dl>
        </div>
    </div>

    {{-- Sidebar de recursos --}}
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
            <div class="text-xs text-gray-500 uppercase mb-1">Estado</div>
            @if ($tenant->is_active)
                <div class="text-emerald-700 font-semibold">Activa</div>
            @else
                <div class="text-gray-500 font-semibold">Inactiva</div>
            @endif
            <div class="text-xs text-gray-400 mt-2">Creada {{ $tenant->created_at?->format('d/m/Y H:i') }}</div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-semibold">Sucursales</span>
                <a href="{{ route('admin.sucursales.index', $tenant) }}" class="text-xs text-sky-600 hover:text-sky-700">Gestionar →</a>
            </div>
            <div class="px-5 py-3 text-2xl font-bold text-gray-900">{{ $tenant->sucursales->count() }}</div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-semibold">Series</span>
                <a href="{{ route('admin.series.index', $tenant) }}" class="text-xs text-sky-600 hover:text-sky-700">Gestionar →</a>
            </div>
            <div class="px-5 py-3 text-2xl font-bold text-gray-900">{{ $tenant->series->count() }}</div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
            <div class="text-xs text-gray-500 uppercase mb-1">Usuario asignado</div>
            @if ($tenant->user)
                <div class="text-gray-900 text-sm">{{ $tenant->user->name }}</div>
                <div class="text-xs text-gray-500">{{ $tenant->user->email }}</div>
            @else
                <div class="text-gray-500 text-sm">Sin asignar</div>
            @endif
        </div>
    </div>
</div>
@endsection
