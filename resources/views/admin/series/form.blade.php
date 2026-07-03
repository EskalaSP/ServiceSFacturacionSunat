@extends('admin.layouts.app')

@php $editando = $serie->exists; @endphp

@section('title', $editando ? 'Editar serie' : 'Nueva serie')
@section('heading', ($editando ? 'Editar serie' : 'Nueva serie') . " — {$tenant->razon_social}")
@section('heading_actions')
    <a href="{{ route('admin.series.index', $tenant) }}" class="text-sm text-gray-600 hover:text-gray-800">← Volver</a>
@endsection

@section('content')
<form method="POST"
      action="{{ $editando ? route('admin.series.update', [$tenant, $serie]) : route('admin.series.store', $tenant) }}"
      class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 max-w-2xl"
      x-data='@json(["tipo" => old("tipo_documento", $serie->tipo_documento ?? "01"), "prefijos" => $prefijos])'>
    @csrf
    @if ($editando) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de documento *</label>
            <select name="tipo_documento" x-model="tipo" required {{ $editando ? 'disabled' : '' }}
                    class="w-full rounded-md border-gray-300 shadow-sm text-sm {{ $editando ? 'bg-gray-100' : '' }}">
                <option value="01">Factura (01) — F</option>
                <option value="03">Boleta (03) — B</option>
                <option value="07">Nota de Crédito (07) — F/B</option>
                <option value="08">Nota de Débito (08) — F/B</option>
                <option value="09">Guía Remitente (09) — T</option>
                <option value="31">Guía Transportista (31) — V</option>
                <option value="20">Retención (20) — R</option>
                <option value="40">Percepción (40) — P</option>
            </select>
            @if ($editando)
                <input type="hidden" name="tipo_documento" value="{{ $serie->tipo_documento }}">
                <p class="text-xs text-gray-500 mt-1">El tipo no se puede cambiar en una serie existente.</p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Serie *</label>
            <input type="text" name="serie" value="{{ old('serie', $serie->serie) }}" required
                   maxlength="4" minlength="4" pattern="[A-Z][A-Z0-9]{3}"
                   {{ $editando ? 'readonly' : '' }}
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono uppercase {{ $editando ? 'bg-gray-100' : '' }}"
                   placeholder="F001">
            <p class="text-xs text-gray-500 mt-1">
                4 caracteres. Prefijo válido:
                <span x-text="prefijos[tipo]?.join(' o ')"></span>
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Correlativo actual</label>
            <input type="number" name="correlativo" value="{{ old('correlativo', $serie->correlativo ?? 0) }}" min="0"
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono">
            <p class="text-xs text-gray-500 mt-1">Próximo doc será {{ '{correlativo + 1}' }}. Útil al migrar desde otro proveedor.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal</label>
            <select name="sucursal_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">— Sin asignar —</option>
                @foreach ($sucursales as $suc)
                    <option value="{{ $suc->id }}" @selected(old('sucursal_id', $serie->sucursal_id) == $suc->id)>
                        {{ $suc->nombre }} ({{ $suc->cod_local }})
                    </option>
                @endforeach
            </select>
        </div>

        <label class="md:col-span-2 inline-flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $serie->is_active ?? true))
                   class="rounded border-gray-300 text-sky-600">
            Serie activa
        </label>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
        <a href="{{ route('admin.series.index', $tenant) }}" class="px-4 py-2 text-sm text-gray-700">Cancelar</a>
        <button type="submit" class="px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
            {{ $editando ? 'Guardar cambios' : 'Crear serie' }}
        </button>
    </div>
</form>
@endsection
