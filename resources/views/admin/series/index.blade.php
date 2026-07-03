@extends('admin.layouts.app')

@section('title', 'Series')
@section('heading', "Series — {$tenant->razon_social}")
@section('heading_actions')
    <div class="inline-flex gap-2">
        <a href="{{ route('admin.empresas.show', $tenant) }}" class="text-sm text-gray-600 hover:text-gray-800">← Volver a empresa</a>
        <a href="{{ route('admin.series.create', $tenant) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
            + Nueva serie
        </a>
    </div>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="text-left px-4 py-3">Tipo</th>
                <th class="text-left px-4 py-3">Serie</th>
                <th class="text-left px-4 py-3">Correlativo</th>
                <th class="text-left px-4 py-3">Sucursal</th>
                <th class="text-left px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($series as $s)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <span class="text-xs uppercase font-medium text-gray-700">{{ $tiposNombre[$s->tipo_documento] ?? $s->tipo_documento }}</span>
                        <span class="text-xs text-gray-400 ml-1">({{ $s->tipo_documento }})</span>
                    </td>
                    <td class="px-4 py-3 font-mono font-semibold">{{ $s->serie }}</td>
                    <td class="px-4 py-3 font-mono">{{ str_pad((string) $s->correlativo, 8, '0', STR_PAD_LEFT) }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $s->sucursal?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($s->is_active)
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Activa</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Inactiva</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex gap-3">
                            <a href="{{ route('admin.series.edit', [$tenant, $s]) }}" class="text-sky-600 hover:text-sky-700 text-sm">Editar</a>
                            <form method="POST" action="{{ route('admin.series.toggle', [$tenant, $s]) }}" class="inline">
                                @csrf
                                <button type="submit"
                                        class="text-sm {{ $s->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                    {{ $s->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.series.destroy', [$tenant, $s]) }}" class="inline"
                                  onsubmit="return confirm('¿Eliminar serie? Esto no borra los documentos ya emitidos.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-700 text-sm">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Aún no hay series. Crea al menos F001 (facturas) y B001 (boletas).</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
