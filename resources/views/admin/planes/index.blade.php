@extends('admin.layouts.app')

@section('title', 'Planes')
@section('heading', 'Planes de suscripción')
@section('heading_actions')
    <a href="{{ route('admin.planes.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
        + Nuevo plan
    </a>
@endsection

@section('content')
<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="text-left px-4 py-3">Orden</th>
                <th class="text-left px-4 py-3">Slug</th>
                <th class="text-left px-4 py-3">Nombre</th>
                <th class="text-left px-4 py-3">Precio mensual</th>
                <th class="text-left px-4 py-3">Precio anual</th>
                <th class="text-left px-4 py-3">Docs/mes</th>
                <th class="text-left px-4 py-3">Features</th>
                <th class="text-left px-4 py-3">Estado</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($planes as $p)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $p->sort_order }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $p->slug }}</td>
                    <td class="px-4 py-3 font-semibold text-gray-900">{{ $p->name }}</td>
                    <td class="px-4 py-3">S/ {{ number_format((float) $p->price_monthly, 2) }}</td>
                    <td class="px-4 py-3">{{ $p->price_yearly ? 'S/ ' . number_format((float) $p->price_yearly, 2) : '—' }}</td>
                    <td class="px-4 py-3 font-mono">
                        @php $docs = $p->getLimit('documents_month', 0); @endphp
                        {{ $docs === -1 ? '∞' : $docs }}
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">{{ count($p->features ?? []) }} activas</td>
                    <td class="px-4 py-3">
                        @if ($p->is_active)
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Activo</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex gap-3">
                            <a href="{{ route('admin.planes.edit', $p) }}" class="text-sky-600 hover:text-sky-700 text-sm">Editar</a>
                            <form method="POST" action="{{ route('admin.planes.toggle', $p) }}" class="inline">
                                @csrf
                                <button type="submit"
                                        class="text-sm {{ $p->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                    {{ $p->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.planes.destroy', $p) }}" class="inline"
                                  onsubmit="return confirm('¿Eliminar plan {{ $p->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-700 text-sm">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-10 text-center text-gray-500">Aún no hay planes definidos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
