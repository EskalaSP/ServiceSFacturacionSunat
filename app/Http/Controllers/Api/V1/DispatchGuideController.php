<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateDispatchGuideAction;
use App\Actions\Documents\UpdateDispatchGuideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDispatchGuideRequest;
use App\Http\Resources\Api\V1\DispatchGuideResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CachesPdf;
use App\Jobs\SendDispatchGuideToSunat;
use App\Models\DispatchGuide;
use App\Services\Greenter\GreenterService;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatchGuideController extends Controller
{
    use ApiResponse, CachesPdf;

    public function store(StoreDispatchGuideRequest $request, CreateDispatchGuideAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $data = $request->validated();
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $guide = $action->execute($tenant, $data, $enviarAuto);

            $msg = $enviarAuto
                ? 'Guía de remisión creada y encolada para envío a SUNAT.'
                : 'Guía de remisión creada en estado pendiente. Use POST /guias-remision/{id}/enviar para enviarla a SUNAT.';

            return $this->createdWithGrtWarning(new DispatchGuideResource($guide), $msg, $tenant, $data['tipo_documento'] ?? '09');
        } catch (\Throwable $e) {
            return $this->error('Error al crear guía: '.$e->getMessage(), 500);
        }
    }

    /**
     * Atajo para emitir Guía de Remisión Transportista (tipo 31).
     * Forza tipo_documento='31' antes de la validación via Form Request.
     */
    public function storeTransportista(StoreDispatchGuideRequest $request, CreateDispatchGuideAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $data = $request->validated();
        $data['tipo_documento'] = '31'; // forzar GRT
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $guide = $action->execute($tenant, $data, $enviarAuto);

            $msg = $enviarAuto
                ? 'Guía de Remisión Transportista creada y encolada para envío a SUNAT.'
                : 'Guía de Remisión Transportista creada en estado pendiente. Use POST /guias-remision/{id}/enviar para enviarla a SUNAT.';

            return $this->createdWithGrtWarning(new DispatchGuideResource($guide), $msg, $tenant, '31');
        } catch (\Throwable $e) {
            return $this->error('Error al crear guía transportista: '.$e->getMessage(), 500);
        }
    }

    /**
     * Envuelve la respuesta 201 agregando advertencia cuando se emite una GRT
     * (tipo 31) contra el entorno beta de SUNAT, que no valida GRT completamente.
     */
    private function createdWithGrtWarning(DispatchGuideResource $resource, string $mensaje, $tenant, string $tipoDocumento): JsonResponse
    {
        $cuerpo = [
            'estado' => 'exito',
            'mensaje' => $mensaje,
            'datos' => $resource,
        ];

        if ($tipoDocumento === '31' && ($tenant->environment ?? null) === 'beta') {
            $cuerpo['advertencias'] = [[
                'codigo' => 'grt_beta_no_soportado',
                'mensaje' => 'SUNAT beta no valida completamente las Guías de Remisión Transportista. Para pruebas realistas cambia entorno a "production" en PUT /empresa.',
            ]];
        }

        return response()->json($cuerpo, 201);
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);

        if ($guide->sunat_status === 'aceptado') {
            return $this->error('Esta guía de remisión ya fue aceptada por SUNAT.', 422);
        }

        $guide->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendDispatchGuideToSunat::dispatch($guide->id);
        $guide->update(['sunat_status' => 'enviado']);

        return $this->success(
            new DispatchGuideResource($guide->fresh()),
            'Guía de remisión enviada a SUNAT.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $guides = DispatchGuide::forTenant($tenant->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('por_pagina', 15));

        return $this->success([
            'datos' => DispatchGuideResource::collection($guides),
            'paginacion' => [
                'pagina_actual' => $guides->currentPage(),
                'ultima_pagina' => $guides->lastPage(),
                'por_pagina' => $guides->perPage(),
                'total' => $guides->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);

        return $this->success(new DispatchGuideResource($guide));
    }

    public function update(StoreDispatchGuideRequest $request, int $id, UpdateDispatchGuideAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);

        if (! in_array($guide->sunat_status, ['pendiente', 'rechazado'])) {
            return $this->error('Solo guías pendientes o rechazadas pueden editarse.', 422);
        }

        try {
            $guide = $action->execute($guide, $request->validated());

            return $this->success(new DispatchGuideResource($guide), 'Guía actualizada y reenviada a SUNAT.');
        } catch (\Throwable $e) {
            return $this->error('Error al actualizar guía: '.$e->getMessage(), 500);
        }
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        $content = $this->getCachedPdfContent($guide, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($guide, $tenant, $format);
            $this->cachePdfContent($guide, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$guide->numero_completo}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService;
        $content = $storage->getXmlContent($guide);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$guide->numero_completo}.xml\"",
        ]);
    }

    public function checkStatus(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $guide = DispatchGuide::forTenant($tenant->id)->findOrFail($id);

        if (! $guide->ticket) {
            $isWaitingForSend = in_array($guide->sunat_status, ['pendiente', 'enviado'], true);
            $statusCode = $isWaitingForSend ? 202 : 409;

            return response()->json([
                'estado' => $isWaitingForSend ? 'pendiente' : 'error',
                'mensaje' => $isWaitingForSend
                    ? 'La guía todavía no tiene ticket SUNAT. Primero debe ejecutarse el envío inicial de la guía.'
                    : 'La guía no tiene ticket SUNAT porque el envío inicial falló antes de recibir ticket.',
                'guia' => [
                    'id' => $guide->id,
                    'numero_completo' => $guide->numero_completo,
                    'estado_sunat' => $guide->sunat_status,
                    'codigo_sunat' => $guide->sunat_code,
                    'descripcion_sunat' => $guide->sunat_description,
                    'tiene_ticket' => false,
                ],
                'siguiente_accion' => ! $isWaitingForSend
                    ? "Corrija el error indicado y use POST /guias-remision/{$guide->id}/enviar para reintentar."
                    : ($guide->sunat_status === 'pendiente'
                    ? "Use POST /guias-remision/{$guide->id}/enviar para enviar la guía a SUNAT."
                    : 'Verifique que el worker de colas esté ejecutándose: php artisan queue:work --queue=default'),
            ], $statusCode);
        }

        $isRetryableTokenError = $guide->sunat_status === 'rechazado'
            && $guide->sunat_code === 'API'
            && str_contains((string) $guide->sunat_description, 'Token NO existe');

        if (in_array($guide->sunat_status, ['aceptado', 'rechazado']) && ! $isRetryableTokenError) {
            return $this->success(new DispatchGuideResource($guide), 'Estado ya resuelto.');
        }

        try {
            $service = new GreenterService($tenant);
            $storage = new DocumentStorageService;
            $result = $service->getGreStatus($guide->ticket);

            if ($result['success']) {
                $accepted = $result['accepted'] ?? true;
                $guide->update([
                    'sunat_status' => $accepted ? 'aceptado' : 'rechazado',
                    'sunat_code' => isset($result['code']) ? substr((string) $result['code'], 0, 20) : null,
                    'sunat_description' => isset($result['description']) ? substr($result['description'], 0, 500) : null,
                    'sent_at' => now(),
                ]);

                if (! empty($result['cdr_zip'])) {
                    $storage->storeCdr($guide, $tenant, $result['cdr_zip']);
                }

                return $this->success(new DispatchGuideResource($guide->fresh()));
            }

            $errorCode = (string) ($result['error_code'] ?? '');
            if (in_array($errorCode, ['0', '187', '401'], true)) {
                return $this->error(
                    'Error al consultar estado: ' . ($result['error_message'] ?? 'Respuesta inválida de SUNAT.'),
                    202
                );
            }

            $guide->update([
                'sunat_status' => 'rechazado',
                'sunat_code' => substr($errorCode, 0, 20),
                'sunat_description' => isset($result['error_message']) ? substr($result['error_message'], 0, 500) : null,
            ]);

            return $this->success(new DispatchGuideResource($guide->fresh()), 'Estado ya resuelto.');
        } catch (\Throwable $e) {
            return $this->error('Error al consultar estado: '.$e->getMessage(), 500);
        }
    }
}
