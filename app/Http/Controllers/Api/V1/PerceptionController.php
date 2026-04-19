<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreatePerceptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePerceptionRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\SendPerceptionToSunat;
use App\Models\Perception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PerceptionController extends Controller
{
    use ApiResponse;

    public function store(StorePerceptionRequest $request, CreatePerceptionAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $perception = $action->execute($tenant, $request->validated(), $enviarAuto);

            $msg = $enviarAuto
                ? 'Percepción creada y encolada para envío a SUNAT.'
                : 'Percepción creada en estado pendiente. Use POST /perceptions/{id}/enviar para enviarla a SUNAT.';

            return response()->json([
                'estado' => 'exito',
                'mensaje' => $msg,
                'datos' => $this->formatPerception($perception),
            ], 201);
        } catch (\Throwable $e) {
            return $this->error('Error al crear percepción: ' . $e->getMessage(), 500);
        }
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $perception = Perception::forTenant($tenant->id)->findOrFail($id);

        if ($perception->sunat_status === 'aceptado') {
            return $this->error('Esta percepción ya fue aceptada por SUNAT.', 422);
        }

        $perception->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendPerceptionToSunat::dispatch($perception->id);
        $perception->update(['sunat_status' => 'enviado']);

        return $this->success(
            $this->formatPerception($perception->fresh()),
            'Percepción enviada a SUNAT.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Perception::forTenant($tenant->id)->orderByDesc('created_at');

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

        if ($request->has('cliente_num_doc')) {
            $query->where('cliente_num_doc', $request->input('cliente_num_doc'));
        }

        $perceptions = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'datos' => $perceptions->map(fn (Perception $p) => $this->formatPerception($p)),
            'paginacion' => [
                'total' => $perceptions->total(),
                'pagina_actual' => $perceptions->currentPage(),
                'ultima_pagina' => $perceptions->lastPage(),
                'por_pagina' => $perceptions->perPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $perception = Perception::forTenant($tenant->id)->with('items')->findOrFail($id);

        return $this->success($this->formatPerception($perception, true));
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $perception = Perception::forTenant($tenant->id)->findOrFail($id);

        if (! $perception->xml_path || ! Storage::disk('public')->exists($perception->xml_path)) {
            return $this->error('XML no disponible', 404);
        }

        return response(Storage::disk('public')->get($perception->xml_path), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$perception->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $perception = Perception::forTenant($tenant->id)->findOrFail($id);

        if (! $perception->cdr_path || ! Storage::disk('public')->exists($perception->cdr_path)) {
            return $this->error('CDR no disponible', 404);
        }

        return response(Storage::disk('public')->get($perception->cdr_path), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$perception->numero_completo}.zip\"",
        ]);
    }

    private function formatPerception(Perception $perception, bool $withItems = false): array
    {
        $data = [
            'id' => $perception->id,
            'serie' => $perception->serie,
            'correlativo' => $perception->correlativo,
            'numero' => $perception->numero_completo,
            'fecha_emision' => $perception->fecha_emision->format('Y-m-d'),
            'cliente' => [
                'tipo_doc' => $perception->cliente_tipo_doc,
                'num_doc' => $perception->cliente_num_doc,
                'razon_social' => $perception->cliente_razon_social,
            ],
            'regimen' => $perception->regimen,
            'tasa' => (float) $perception->tasa,
            'imp_percibido' => (float) $perception->imp_percibido,
            'imp_cobrado' => (float) $perception->imp_cobrado,
            'sunat_status' => $perception->sunat_status,
            'sunat_code' => $perception->sunat_code,
            'created_at' => $perception->created_at->toIso8601String(),
        ];

        if ($withItems && $perception->relationLoaded('items')) {
            $data['documentos'] = $perception->items->map(fn ($item) => [
                'tipo_doc' => $item->tipo_doc,
                'num_doc' => $item->num_doc,
                'fecha_emision' => $item->fecha_emision_doc->format('Y-m-d'),
                'imp_total' => (float) $item->imp_total,
                'moneda' => $item->moneda,
                'fecha_percepcion' => $item->fecha_percepcion->format('Y-m-d'),
                'imp_percibido' => (float) $item->imp_percibido,
                'imp_cobrar' => (float) $item->imp_cobrar,
            ])->toArray();
        }

        return $data;
    }
}
