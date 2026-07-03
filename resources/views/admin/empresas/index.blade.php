@extends('admin.layouts.app')

@section('title', 'Empresas')
@section('heading', 'Empresas')
@section('heading_actions')
    <a href="{{ route('admin.empresas.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 text-white rounded-md text-sm font-medium hover:bg-sky-700">
        + Nueva empresa
    </a>
@endsection

@section('content')
<form method="GET" class="mb-5 bg-white p-4 rounded-lg shadow-sm border border-gray-100 grid grid-cols-1 sm:grid-cols-4 gap-3">
    <input type="text" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="RUC, razón social..."
           class="col-span-2 rounded-md border-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm">
    <select name="plan" class="rounded-md border-gray-300 shadow-sm text-sm">
        <option value="">Todos los planes</option>
        <option value="free" @selected(($filtros['plan'] ?? '') === 'free')>Free</option>
        <option value="pro" @selected(($filtros['plan'] ?? '') === 'pro')>Pro</option>
        <option value="business" @selected(($filtros['plan'] ?? '') === 'business')>Business</option>
    </select>
    <div class="flex gap-2">
        <select name="estado" class="flex-1 rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">Todos</option>
            <option value="activa" @selected(($filtros['estado'] ?? '') === 'activa')>Activas</option>
            <option value="inactiva" @selected(($filtros['estado'] ?? '') === 'inactiva')>Inactivas</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm hover:bg-gray-900">Buscar</button>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="text-left px-4 py-3">RUC</th>
                    <th class="text-left px-4 py-3">Razón social</th>
                    <th class="text-left px-4 py-3">Entorno</th>
                    <th class="text-left px-4 py-3">Régimen</th>
                    <th class="text-left px-4 py-3">Plan</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($empresas as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono">{{ $t->ruc }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $t->razon_social }}</div>
                            @if ($t->nombre_comercial)
                                <div class="text-xs text-gray-500">{{ $t->nombre_comercial }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs uppercase font-medium
                                {{ $t->environment === 'production' ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $t->environment }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ $t->tax_regime }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs uppercase font-medium bg-violet-100 text-violet-700 px-2 py-0.5 rounded">
                                {{ $t->plan }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($t->is_active)
                                <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Activa</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Inactiva</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex gap-2">
                                <a href="{{ route('admin.empresas.show', $t) }}" class="text-sky-600 hover:text-sky-700 text-sm">Ver</a>
                                <a href="{{ route('admin.empresas.edit', $t) }}" class="text-gray-600 hover:text-gray-800 text-sm">Editar</a>
                                <form method="POST" action="{{ route('admin.empresas.toggle', $t) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="text-sm {{ $t->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                        {{ $t->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                            No hay empresas que coincidan con los filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-100">
        {{ $empresas->links() }}
    </div>
</div>
@endsection
