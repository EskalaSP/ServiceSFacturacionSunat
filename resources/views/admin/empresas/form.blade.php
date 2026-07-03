@extends('admin.layouts.app')

@php
    $editando = $tenant->exists;
@endphp

@section('title', $editando ? 'Editar empresa' : 'Nueva empresa')
@section('heading', $editando ? "Editar: {$tenant->razon_social}" : 'Nueva empresa')
@section('heading_actions')
    <a href="{{ $editando ? route('admin.empresas.show', $tenant) : route('admin.empresas.index') }}"
       class="text-sm text-gray-600 hover:text-gray-800">← Volver</a>
@endsection

@section('content')
<form method="POST"
      action="{{ $editando ? route('admin.empresas.update', $tenant) : route('admin.empresas.store') }}"
      enctype="multipart/form-data"
      class="space-y-6"
      x-data='@json([
        "telefonos" => old("telefonos", $tenant->telefonos ?? []),
        "emails" => old("emails", $tenant->emails ?? []),
        "cuentas" => old("cuentas_bancarias", $tenant->cuentas_bancarias ?? []),
        "billeteras" => old("billeteras_digitales", $tenant->billeteras_digitales ?? []),
        "tax_regime" => old("tax_regime", $tenant->tax_regime ?? "general"),
      ])'>
    @csrf
    @if ($editando) @method('PUT') @endif

    {{-- 1. IDENTIDAD --}}
    @include('admin.empresas.partials._section', ['title' => '1. Identidad', 'subtitle' => 'RUC y datos legales de la empresa'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 -mt-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">RUC *</label>
            <input type="text" name="ruc" value="{{ old('ruc', $tenant->ruc) }}" maxlength="11" pattern="\d{11}"
                   {{ $editando ? 'readonly' : 'required' }}
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm {{ $editando ? 'bg-gray-100' : '' }}">
            @if (! $editando)<p class="text-xs text-gray-500 mt-1">11 dígitos, empieza con 10/15/17/20</p>@endif
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Razón social *</label>
            <input type="text" name="razon_social" value="{{ old('razon_social', $tenant->razon_social) }}" required maxlength="255"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre comercial</label>
            <input type="text" name="nombre_comercial" value="{{ old('nombre_comercial', $tenant->nombre_comercial) }}" maxlength="255"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
    </div>

    {{-- 2. UBICACIÓN --}}
    @include('admin.empresas.partials._section', ['title' => '2. Ubicación', 'subtitle' => 'Dirección fiscal y contacto'])
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 -mt-4 mb-6">
        <div class="md:col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección fiscal</label>
            <input type="text" name="direccion" value="{{ old('direccion', $tenant->direccion) }}" maxlength="500"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ubigeo</label>
            <input type="text" name="ubigeo" value="{{ old('ubigeo', $tenant->ubigeo) }}" maxlength="6" pattern="\d{6}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
            <input type="text" name="departamento" value="{{ old('departamento', $tenant->departamento) }}" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Provincia</label>
            <input type="text" name="provincia" value="{{ old('provincia', $tenant->provincia) }}" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Distrito</label>
            <input type="text" name="distrito" value="{{ old('distrito', $tenant->distrito) }}" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>

        {{-- Teléfonos dinámicos --}}
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfonos</label>
            <template x-for="(tel, i) in telefonos" :key="i">
                <div class="flex gap-2 mb-2">
                    <input type="text" x-model="telefonos[i]" :name="`telefonos[${i}]`" maxlength="20"
                           class="flex-1 rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="button" @click="telefonos.splice(i, 1)" class="text-red-500 hover:text-red-600 text-sm px-2">✕</button>
                </div>
            </template>
            <button type="button" @click="telefonos.push('')" x-show="telefonos.length < 5"
                    class="text-sky-600 hover:text-sky-700 text-sm">+ Añadir teléfono</button>
        </div>

        {{-- Emails dinámicos --}}
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Emails</label>
            <template x-for="(em, i) in emails" :key="i">
                <div class="flex gap-2 mb-2">
                    <input type="email" x-model="emails[i]" :name="`emails[${i}]`" maxlength="100"
                           class="flex-1 rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="button" @click="emails.splice(i, 1)" class="text-red-500 hover:text-red-600 text-sm px-2">✕</button>
                </div>
            </template>
            <button type="button" @click="emails.push('')" x-show="emails.length < 5"
                    class="text-sky-600 hover:text-sky-700 text-sm">+ Añadir email</button>
        </div>
    </div>

    {{-- 3. CREDENCIALES SUNAT --}}
    @include('admin.empresas.partials._section', ['title' => '3. Credenciales SUNAT', 'subtitle' => 'Usuario SOL, certificado y entorno'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 -mt-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Usuario SOL *</label>
            <input type="text" name="sol_user" value="{{ old('sol_user', $editando ? '' : 'MODDATOS') }}" required maxlength="50"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            @if ($editando)<p class="text-xs text-gray-500 mt-1">Deja vacío para no cambiar</p>@endif
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Clave SOL {{ $editando ? '' : '*' }}</label>
            <input type="password" name="sol_pass" value="{{ old('sol_pass') }}" {{ $editando ? '' : 'required' }} minlength="4"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            @if ($editando)<p class="text-xs text-gray-500 mt-1">Deja vacío para no cambiar</p>@endif
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Entorno *</label>
            <select name="environment" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="beta"       @selected(old('environment', $tenant->environment) === 'beta')>Beta (pruebas)</option>
                <option value="production" @selected(old('environment', $tenant->environment) === 'production')>Producción</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Certificado digital (.pfx / .p12)</label>
            <input type="file" name="certificado" accept=".pfx,.p12"
                   class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
            @if ($tenant->certificate_path)<p class="text-xs text-emerald-600 mt-1">✔ Certificado actual cargado</p>@endif
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña del certificado</label>
            <input type="password" name="contrasena_certificado" value="{{ old('contrasena_certificado') }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
    </div>

    {{-- 4. SIRE --}}
    @include('admin.empresas.partials._section', ['title' => '4. SIRE (opcional)', 'subtitle' => 'Registro de Compras Electrónico'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 -mt-4 mb-6">
        <label class="md:col-span-3 inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="sire_enabled" value="1" @checked(old('sire_enabled', $tenant->sire_enabled))
                   class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
            Activar SIRE para esta empresa
        </label>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Client ID SIRE</label>
            <input type="text" name="sire_client_id" value="{{ old('sire_client_id', $tenant->sire_client_id) }}" maxlength="100"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret SIRE</label>
            <input type="password" name="sire_client_secret" value="{{ old('sire_client_secret') }}" maxlength="200"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                   placeholder="{{ $editando && $tenant->sire_client_secret ? '••••••• (sin cambios)' : '' }}">
        </div>
    </div>

    {{-- 5. RÉGIMEN TRIBUTARIO --}}
    @include('admin.empresas.partials._section', ['title' => '5. Régimen tributario', 'subtitle' => 'Define cómo se calcula el IGV'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 -mt-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Régimen *</label>
            <select name="tax_regime" x-model="tax_regime" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="general">General (18% IGV)</option>
                <option value="mype_restaurantes">MYPE Restaurantes (Ley 31556)</option>
                <option value="nrus">NRUS (solo boletas, 0% IGV)</option>
            </select>
        </div>
        <div x-show="tax_regime !== 'nrus'">
            <label class="block text-sm font-medium text-gray-700 mb-1">IGV override (%)</label>
            <input type="number" step="0.01" name="igv_rate_override" value="{{ old('igv_rate_override', $tenant->igv_rate_override) }}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="18.00">
            <p class="text-xs text-gray-500 mt-1">Solo si necesitas forzar tasa distinta</p>
        </div>
        <div x-show="tax_regime === 'nrus'">
            <label class="block text-sm font-medium text-gray-700 mb-1">Categoría NRUS</label>
            <select name="nrus_categoria" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">—</option>
                <option value="1" @selected(old('nrus_categoria', $tenant->nrus_categoria) == '1')>Cat. 1 (S/20 mensual — hasta S/5k ventas)</option>
                <option value="2" @selected(old('nrus_categoria', $tenant->nrus_categoria) == '2')>Cat. 2 (S/50 mensual — hasta S/8k ventas)</option>
            </select>
        </div>
    </div>

    {{-- 6. PLAN Y LÍMITES --}}
    @include('admin.empresas.partials._section', ['title' => '6. Plan y límites', 'subtitle' => 'Plan asignado a la empresa'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 -mt-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Plan *</label>
            <select name="plan" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                @foreach (['free' => 'Free', 'pro' => 'Pro', 'business' => 'Business'] as $slug => $label)
                    <option value="{{ $slug }}" @selected(old('plan', $tenant->plan) === $slug)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Documentos SUNAT/mes *</label>
            <input type="number" name="max_documents_month" value="{{ old('max_documents_month', $tenant->max_documents_month ?? 20) }}"
                   required min="0" max="99999"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            <p class="text-xs text-gray-500 mt-1">0 = ilimitado</p>
        </div>
        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tenant->is_active ?? true))
                       class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                Empresa activa
            </label>
        </div>
    </div>

    {{-- 7. COMERCIAL --}}
    @include('admin.empresas.partials._section', ['title' => '7. Comercial', 'subtitle' => 'Webhook, logo, mensajes y pagos'])
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 -mt-4 mb-6">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
            <input type="url" name="webhook_url" value="{{ old('webhook_url', $tenant->webhook_url) }}" maxlength="500"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="https://tu-app.com/sunat-webhook">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Logo (jpg/png/webp)</label>
            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"
                   class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100">
            @if ($tenant->logo_path)<p class="text-xs text-emerald-600 mt-1">✔ Logo actual cargado</p>@endif
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje de agradecimiento (aparece en PDFs)</label>
            <textarea name="mensaje_agradecimiento" rows="2" maxlength="500"
                      class="w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('mensaje_agradecimiento', $tenant->mensaje_agradecimiento) }}</textarea>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje promocional</label>
            <textarea name="mensaje_promocional" rows="2" maxlength="500"
                      class="w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('mensaje_promocional', $tenant->mensaje_promocional) }}</textarea>
        </div>

        {{-- Cuentas bancarias dinámicas --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Cuentas bancarias</label>
            <template x-for="(c, i) in cuentas" :key="i">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-2 mb-2 p-3 bg-gray-50 rounded">
                    <input type="text" x-model="c.banco" :name="`cuentas_bancarias[${i}][banco]`" placeholder="Banco" class="rounded-md border-gray-300 text-sm">
                    <input type="text" x-model="c.tipo" :name="`cuentas_bancarias[${i}][tipo]`" placeholder="Tipo (ahorros/cte)" class="rounded-md border-gray-300 text-sm">
                    <select x-model="c.moneda" :name="`cuentas_bancarias[${i}][moneda]`" class="rounded-md border-gray-300 text-sm">
                        <option value="">Moneda</option>
                        <option value="PEN">PEN</option>
                        <option value="USD">USD</option>
                    </select>
                    <input type="text" x-model="c.numero" :name="`cuentas_bancarias[${i}][numero]`" placeholder="Número" class="rounded-md border-gray-300 text-sm">
                    <input type="text" x-model="c.cci" :name="`cuentas_bancarias[${i}][cci]`" placeholder="CCI (opcional)" class="rounded-md border-gray-300 text-sm">
                    <button type="button" @click="cuentas.splice(i, 1)" class="text-red-500 hover:text-red-600 text-sm">Eliminar</button>
                </div>
            </template>
            <button type="button" x-show="cuentas.length < 5"
                    @click="cuentas.push({banco:'',tipo:'',moneda:'PEN',numero:'',cci:''})"
                    class="text-sky-600 hover:text-sky-700 text-sm">+ Añadir cuenta</button>
        </div>

        {{-- Billeteras digitales --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Billeteras digitales</label>
            <template x-for="(b, i) in billeteras" :key="i">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2 p-3 bg-gray-50 rounded">
                    <input type="text" x-model="b.tipo" :name="`billeteras_digitales[${i}][tipo]`" placeholder="Yape / Plin / Tunki..." class="rounded-md border-gray-300 text-sm">
                    <input type="text" x-model="b.numero" :name="`billeteras_digitales[${i}][numero]`" placeholder="Número" class="rounded-md border-gray-300 text-sm">
                    <button type="button" @click="billeteras.splice(i, 1)" class="text-red-500 hover:text-red-600 text-sm">Eliminar</button>
                </div>
            </template>
            <button type="button" x-show="billeteras.length < 5"
                    @click="billeteras.push({tipo:'',numero:''})"
                    class="text-sky-600 hover:text-sky-700 text-sm">+ Añadir billetera</button>
        </div>
    </div>

    {{-- 8. ASIGNACIÓN --}}
    @include('admin.empresas.partials._section', ['title' => '8. Usuario asignado (opcional)', 'subtitle' => 'Cliente que administra esta empresa'])
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 -mt-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
            <select name="user_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">— Sin asignar —</option>
                @foreach ($usuarios as $u)
                    <option value="{{ $u->id }}" @selected(old('user_id', $tenant->user_id) == $u->id)>
                        {{ $u->name }} ({{ $u->email }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ACCIONES --}}
    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
        <a href="{{ $editando ? route('admin.empresas.show', $tenant) : route('admin.empresas.index') }}"
           class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900">Cancelar</a>
        <button type="submit" class="px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
            {{ $editando ? 'Guardar cambios' : 'Registrar empresa' }}
        </button>
    </div>
</form>
@endsection
