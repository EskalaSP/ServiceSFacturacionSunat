<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Models\SireComprobante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SireComprobanteController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/sire/rce/{periodo}/comprobantes
     *
     * Devuelve los comprobantes ya parseados localmente (después del flujo
     * Propuesta → Ticket → Download → Process).
     */
    public function index(Request $request, string $periodo): JsonResponse
    {
        $tenant = $request->get('tenant');

        $validated = $request->validate([
            'fase'                   => 'sometimes|in:propuesta,preliminar,registrado',
            'cod_tipo_cdp'           => 'sometimes|string|size:2',
            'num_doc_proveedor'      => 'sometimes|string',
            'incluido'               => 'sometimes|boolean',
            'por_pagina' => 'sometimes|integer|min:1|max:200',
            'sort_by'                => 'sometimes|in:fec_emision,mto_total,razon_social_proveedor',
            'sort_dir'               => 'sometimes|in:asc,desc',
        ]);

        $sortBy  = $validated['sort_by']  ?? 'fec_emision';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $perPage = $validated['per_page'] ?? 50;

        $query = SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->when(isset($validated['fase']), fn ($q) => $q->where('fase', $validated['fase']))
            ->when(isset($validated['cod_tipo_cdp']), fn ($q) => $q->where('cod_tipo_cdp', $validated['cod_tipo_cdp']))
            ->when(isset($validated['num_doc_proveedor']), fn ($q) => $q->where('num_doc_proveedor', $validated['num_doc_proveedor']))
            ->when(isset($validated['incluido']), fn ($q) => $q->where('incluido', $validated['incluido']))
            ->orderBy($sortBy, $sortDir);

        $page = $query->paginate($perPage);

        // Totales del filtro actual (útil para UI)
        $totalsQuery = SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->when(isset($validated['fase']), fn ($q) => $q->where('fase', $validated['fase']))
            ->when(isset($validated['cod_tipo_cdp']), fn ($q) => $q->where('cod_tipo_cdp', $validated['cod_tipo_cdp']))
            ->when(isset($validated['num_doc_proveedor']), fn ($q) => $q->where('num_doc_proveedor', $validated['num_doc_proveedor']))
            ->when(isset($validated['incluido']), fn ($q) => $q->where('incluido', $validated['incluido']));

        $totales = $totalsQuery->selectRaw('
            COUNT(*)             as total_comprobantes,
            SUM(mto_bi_gravada)  as suma_bi_gravada,
            SUM(mto_igv)         as suma_igv,
            SUM(mto_total)       as suma_total
        ')->first();

        return $this->success([
            'totales' => [
                'total_comprobantes' => (int) $totales->total_comprobantes,
                'suma_bi_gravada'    => (float) $totales->suma_bi_gravada,
                'suma_igv'           => (float) $totales->suma_igv,
                'suma_total'         => (float) $totales->suma_total,
            ],
            'comprobantes' => $page,
        ]);
    }

    /**
     * GET /v1/sire/rce/{periodo}/comprobantes/{id}
     */
    public function show(Request $request, string $periodo, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');

        $cp = SireComprobante::query()
            ->where('tenant_id', $tenant->id)
            ->where('per_tributario', $periodo)
            ->findOrFail($id);

        return $this->success($cp);
    }
}
