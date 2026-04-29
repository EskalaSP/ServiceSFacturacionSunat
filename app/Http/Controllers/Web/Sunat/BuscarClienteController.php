<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Boleta;
use Illuminate\Http\Request;

class BuscarClienteController extends Controller
{
    public function buscar(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Client::where('tenant_id', $tenant->id)
            ->where(function ($query) use ($q) {
                $query->where('numero_documento', 'like', "%{$q}%")
                    ->orWhere('razon_social', 'like', "%{$q}%");
            })
            ->orderBy('razon_social')
            ->limit(10)
            ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'email']);

        return response()->json($clientes);
    }

    public function buscarDocumento(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $facturas = Invoice::forTenant($tenant->id)
            ->where(function ($query) use ($q) {
                $query->where('serie', 'like', "%{$q}%")
                    ->orWhere('client_razon_social', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(serie, '-', LPAD(correlativo, 8, '0')) LIKE ?", ["%{$q}%"]);
            })
            ->whereIn('sunat_status', ['aceptado', 'pendiente', 'enviado'])
            ->orderByDesc('fecha_emision')
            ->limit(8)
            ->get(['id', 'serie', 'correlativo', 'client_razon_social', 'mto_imp_venta', 'tipo_moneda', 'fecha_emision'])
            ->map(fn ($d) => [
                'id'         => $d->id,
                'tipo_doc'   => '01',
                'numero'     => $d->serie . '-' . str_pad((string) $d->correlativo, 8, '0', STR_PAD_LEFT),
                'cliente'    => $d->client_razon_social,
                'total'      => (float) $d->mto_imp_venta,
                'moneda'     => $d->tipo_moneda,
                'fecha'      => $d->fecha_emision,
            ]);

        $boletas = Boleta::forTenant($tenant->id)
            ->where(function ($query) use ($q) {
                $query->where('serie', 'like', "%{$q}%")
                    ->orWhere('client_razon_social', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(serie, '-', LPAD(correlativo, 8, '0')) LIKE ?", ["%{$q}%"]);
            })
            ->whereIn('sunat_status', ['aceptado', 'pendiente', 'enviado'])
            ->orderByDesc('fecha_emision')
            ->limit(8)
            ->get(['id', 'serie', 'correlativo', 'client_razon_social', 'mto_imp_venta', 'tipo_moneda', 'fecha_emision'])
            ->map(fn ($d) => [
                'id'         => $d->id,
                'tipo_doc'   => '03',
                'numero'     => $d->serie . '-' . str_pad((string) $d->correlativo, 8, '0', STR_PAD_LEFT),
                'cliente'    => $d->client_razon_social,
                'total'      => (float) $d->mto_imp_venta,
                'moneda'     => $d->tipo_moneda,
                'fecha'      => $d->fecha_emision,
            ]);

        return response()->json($facturas->concat($boletas)->sortByDesc('fecha')->values());
    }
}
