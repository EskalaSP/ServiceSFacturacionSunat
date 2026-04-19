<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateRetentionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRetentionRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendRetentionToSunat;
use App\Models\Retention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RetentionController extends Controller
{
    use ApiResponse;

    public function store(StoreRetentionRequest $request, CreateRetentionAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $retention = $action->execute($tenant, $request->validated(), $enviarAuto);

            $msg = $enviarAuto
                ? 'Retención creada y encolada para envío a SUNAT.'
                : 'Retención creada en estado pendiente. Use POST /retentions/{id}/enviar para enviarla a SUNAT.';

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => $this->formatRetention($retention),
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('Error al crear retención: ' . $e->getMessage(), 500);
        }
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $retention = Retention::forTenant($tenant->id)->findOrFail($id);

        if ($retention->sunat_status === 'aceptado') {
            return $this->error('Esta retención ya fue aceptada por SUNAT.', 422);
        }

        $retention->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendRetentionToSunat::dispatch($retention->id);
        $retention->update(['sunat_status' => 'enviado']);

        return $this->success(
            $this->formatRetention($retention->fresh()),
            'Retención enviada a SUNAT.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Retention::forTenant($tenant->id)->orderByDesc('created_at');

        if ($request->has('sunat_status')) {
            $query->status($request->input('sunat_status'));
        }

        if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
            $query->whereBetween('fecha_emision', [
                $request->input('fecha_desde') . ' 00:00:00',
                $request->input('fecha_hasta') . ' 23:59:59',
            ]);
        }

        if ($request->has('serie')) {
            $query->where('serie', $request->input('serie'));
        }

        if ($request->has('proveedor_num_doc')) {
            $query->where('proveedor_num_doc', $request->input('proveedor_num_doc'));
        }

        $retentions = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => $retentions->map(fn (Retention $r) => $this->formatRetention($r)),
            'pagination' => [
                'total' => $retentions->total(),
                'current_page' => $retentions->currentPage(),
                'last_page' => $retentions->lastPage(),
                'per_page' => $retentions->perPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $retention = Retention::forTenant($tenant->id)->with('items')->findOrFail($id);

        return $this->success($this->formatRetention($retention, true));
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $retention = Retention::forTenant($tenant->id)->findOrFail($id);

        if (! $retention->xml_path || ! Storage::disk('public')->exists($retention->xml_path)) {
            return $this->error('XML no disponible', 404);
        }

        return response(Storage::disk('public')->get($retention->xml_path), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$retention->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $retention = Retention::forTenant($tenant->id)->findOrFail($id);

        if (! $retention->cdr_path || ! Storage::disk('public')->exists($retention->cdr_path)) {
            return $this->error('CDR no disponible', 404);
        }

        return response(Storage::disk('public')->get($retention->cdr_path), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$retention->numero_completo}.zip\"",
        ]);
    }

    private function formatRetention(Retention $retention, bool $withItems = false): array
    {
        $data = [
            'id' => $retention->id,
            'serie' => $retention->serie,
            'correlativo' => $retention->correlativo,
            'numero' => $retention->numero_completo,
            'fecha_emision' => $retention->fecha_emision->format('Y-m-d'),
            'proveedor' => [
                'tipo_doc' => $retention->proveedor_tipo_doc,
                'num_doc' => $retention->proveedor_num_doc,
                'razon_social' => $retention->proveedor_razon_social,
            ],
            'regimen' => $retention->regimen,
            'tasa' => (float) $retention->tasa,
            'imp_retenido' => (float) $retention->imp_retenido,
            'imp_pagado' => (float) $retention->imp_pagado,
            'sunat_status' => $retention->sunat_status,
            'sunat_code' => $retention->sunat_code,
            'created_at' => $retention->created_at->toIso8601String(),
        ];

        if ($withItems && $retention->relationLoaded('items')) {
            $data['documentos'] = $retention->items->map(fn ($item) => [
                'tipo_doc' => $item->tipo_doc,
                'num_doc' => $item->num_doc,
                'fecha_emision' => $item->fecha_emision_doc->format('Y-m-d'),
                'imp_total' => (float) $item->imp_total,
                'moneda' => $item->moneda,
                'fecha_retencion' => $item->fecha_retencion->format('Y-m-d'),
                'imp_retenido' => (float) $item->imp_retenido,
                'imp_pagar' => (float) $item->imp_pagar,
            ])->toArray();
        }

        return $data;
    }
}
