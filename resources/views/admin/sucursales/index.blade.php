@extends('admin.layouts.app')

@section('title', 'Sucursales')
@section('heading', "Sucursales — {$tenant->razon_social}")
@section('heading_actions')
    <div class="inline-flex gap-2">
        <a href="{{ route('admin.empresas.show', $tenant) }}" class="text-sm text-gray-600 hover:text-gray-800">← Volver a empresa</a>
        <a href="{{ route('admin.sucursales.create', $tenant) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
            + Nueva sucursal
        </a>
    </div>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="text-left px-4 py-3">Cód. Local</th>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Dirección</th>
                <th class="text-left px-4 py-3">Ubigeo</th>
                <th class="text-left px-4 py-3">Principal</th>
                <th class="text-left px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($sucursales as $s)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono">{{ $s->cod_local }}</td>
                    <td class="px-4 py-3 text-gray-900 font-medium">{{ $s->nombre }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $s->direccion ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $s->ubigeo ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($s->is_principal)
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-sky-100 text-sky-800">Principal</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($s->is_active)
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Activa</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Inactiva</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.sucursales.edit', [$tenant, $s]) }}" class="text-sky-600 hover:text-sky-700 text-sm mr-2">Editar</a>
                        <form method="POST" action="{{ route('admin.sucursales.destroy', [$tenant, $s]) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar sucursal?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-700 text-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Aún no hay sucursales.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
