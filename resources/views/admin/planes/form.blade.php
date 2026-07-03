@extends('admin.layouts.app')

@php
    $editando = $plan->exists;
    $limitLabels = [
        'documents_month' => 'Documentos SUNAT / mes',
        'sucursales' => 'Sucursales',
        'team' => 'Miembros del equipo',
        'productos' => 'Productos',
        'ai_messages' => 'Mensajes de IA / mes',
    ];
    $featureLabels = [
        'sunat' => 'Emisión SUNAT (facturas/boletas/NC/ND)',
        'boletas' => 'Boletas + resumen diario',
        'notas' => 'Notas de crédito/débito',
        'guias' => 'Guías de remisión (GRR/GRT)',
        'retenciones' => 'Retenciones',
        'percepciones' => 'Percepciones',
        'sire' => 'SIRE (Registro de Compras)',
        'webhooks' => 'Webhooks',
        'panel' => 'Panel de control',
        'reportes' => 'Reportes avanzados',
        'export_zip' => 'Exportación masiva ZIP',
        'ai_assistant' => 'Asistente IA',
    ];
@endphp

@section('title', $editando ? 'Editar plan' : 'Nuevo plan')
@section('heading', $editando ? "Editar: {$plan->name}" : 'Nuevo plan')
@section('heading_actions')
    <a href="{{ route('admin.planes.index') }}" class="text-sm text-gray-600 hover:text-gray-800">← Volver</a>
@endsection

@section('content')
<form method="POST"
      action="{{ $editando ? route('admin.planes.update', $plan) : route('admin.planes.store') }}"
      class="space-y-6 max-w-4xl">
    @csrf
    @if ($editando) @method('PUT') @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Identidad y precio</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
                <input type="text" name="slug" value="{{ old('slug', $plan->slug) }}" required maxlength="50"
                       pattern="[a-z0-9_-]+"
                       {{ $editando ? 'readonly' : '' }}
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono {{ $editando ? 'bg-gray-100' : '' }}"
                       placeholder="pro, business...">
                <p class="text-xs text-gray-500 mt-1">Solo minúsculas, números, guiones. Se usa en la API.</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre visible *</label>
                <input type="text" name="name" value="{{ old('name', $plan->name) }}" required maxlength="100"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio mensual (S/) *</label>
                <input type="number" step="0.01" name="price_monthly" value="{{ old('price_monthly', $plan->price_monthly) }}" required min="0"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio anual (S/)</label>
                <input type="number" step="0.01" name="price_yearly" value="{{ old('price_yearly', $plan->price_yearly) }}" min="0"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Orden en el listado</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Límites</h3>
        <p class="text-sm text-gray-500 mb-4">Usa <code>-1</code> para ilimitado. Deja vacío si no aplica.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($limitKeys as $key)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $limitLabels[$key] ?? $key }}</label>
                    <input type="number" name="limits[{{ $key }}]"
                           value="{{ old('limits.' . $key, $plan->limits[$key] ?? '') }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"
                           placeholder="Ej: 200 o -1 (ilimitado)">
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Features incluidos</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            @foreach ($featureKeys as $key)
                <label class="inline-flex items-start gap-2 text-sm p-3 border border-gray-200 rounded hover:bg-gray-50">
                    <input type="checkbox" name="features[]" value="{{ $key }}"
                           @checked(in_array($key, old('features', $plan->features ?? []), true))
                           class="mt-0.5 rounded border-gray-300 text-sky-600">
                    <span class="text-gray-800">{{ $featureLabels[$key] ?? $key }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))
                   class="rounded border-gray-300 text-sky-600">
            Plan activo (visible en el listado público de planes)
        </label>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.planes.index') }}" class="px-4 py-2 text-sm text-gray-700">Cancelar</a>
        <button type="submit" class="px-6 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
            {{ $editando ? 'Guardar cambios' : 'Crear plan' }}
        </button>
    </div>
</form>
@endsection
