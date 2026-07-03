@extends('admin.layouts.app')

@php $editando = $sucursal->exists; @endphp

@section('title', $editando ? 'Editar sucursal' : 'Nueva sucursal')
@section('heading', ($editando ? 'Editar sucursal' : 'Nueva sucursal') . " — {$tenant->razon_social}")
@section('heading_actions')
    <a href="{{ route('admin.sucursales.index', $tenant) }}" class="text-sm text-gray-600 hover:text-gray-800">← Volver</a>
@endsection

@section('content')
<form method="POST"
      action="{{ $editando ? route('admin.sucursales.update', [$tenant, $sucursal]) : route('admin.sucursales.store', $tenant) }}"
      class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 max-w-3xl">
    @csrf
    @if ($editando) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
            <input type="text" name="nombre" value="{{ old('nombre', $sucursal->nombre) }}" required maxlength="100"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Código local (SUNAT) *</label>
            <input type="text" name="cod_local" value="{{ old('cod_local', $sucursal->cod_local ?? '0000') }}" required
                   maxlength="4" minlength="4" pattern="\d{4}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono">
            <p class="text-xs text-gray-500 mt-1">4 dígitos. Principal usa 0000.</p>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
            <input type="text" name="direccion" value="{{ old('direccion', $sucursal->direccion) }}" maxlength="500"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ubigeo</label>
            <input type="text" name="ubigeo" value="{{ old('ubigeo', $sucursal->ubigeo) }}" maxlength="6" pattern="\d{6}"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
            <input type="text" name="telefono" value="{{ old('telefono', $sucursal->telefono) }}" maxlength="20"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $sucursal->email) }}" maxlength="100"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="hidden" name="is_principal" value="0">
            <input type="checkbox" name="is_principal" value="1" @checked(old('is_principal', $sucursal->is_principal))
                   class="rounded border-gray-300 text-sky-600">
            Es sucursal principal
        </label>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sucursal->is_active ?? true))
                   class="rounded border-gray-300 text-sky-600">
            Sucursal activa
        </label>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
        <a href="{{ route('admin.sucursales.index', $tenant) }}" class="px-4 py-2 text-sm text-gray-700">Cancelar</a>
        <button type="submit" class="px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
            {{ $editando ? 'Guardar cambios' : 'Crear sucursal' }}
        </button>
    </div>
</form>
@endsection
