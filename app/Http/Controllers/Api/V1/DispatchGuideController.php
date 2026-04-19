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
        $enviarAuto = $request->boolean('enviar_automatico', true);

        try {
            $guide = $action->execute($tenant, $request->validated(), $enviarAuto);

            $msg = $enviarAuto
                ? 'Guía de remisión creada y encolada para envío a SUNAT.'
                : 'Guía de remisión creada en estado pendiente. Use POST /guias-remision/{id}/enviar para enviarla a SUNAT.';

            return $this->created(new DispatchGuideResource($guide), $msg);
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

            return $this->created(new DispatchGuideResource($guide), $msg);
        } catch (\Throwable $e) {
            return $this->error('Error al crear guía transportista: '.$e->getMessage(), 500);
        }
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
            return $this->error('No hay ticket disponible para consultar', 404);
        }

        if (in_array($guide->sunat_status, ['aceptado', 'rechazado'])) {
            return $this->success(new DispatchGuideResource($guide), 'Estado ya resuelto.');
        }

        try {
            $service = new GreenterService($tenant);
            $storage = new DocumentStorageService;
            $api = $service->createApi();
            $result = $api->getStatus($guide->ticket);

            if ($result->isSuccess()) {
                $cdr = $result->getCdrResponse();
                $guide->update([
                    'sunat_status' => $cdr->isAccepted() ? 'aceptado' : 'rechazado',
                    'sunat_code' => $cdr->getCode(),
                    'sunat_description' => $cdr->getDescription(),
                    'sent_at' => now(),
                ]);

                // Guardar CDR en disco
                $cdrZip = $result->getCdrZip();
                if ($cdrZip) {
                    $storage->storeCdr($guide, $tenant, $cdrZip);
                }
            }

            return $this->success(new DispatchGuideResource($guide->fresh()));
        } catch (\Throwable $e) {
            return $this->error('Error al consultar estado: '.$e->getMessage(), 500);
        }
    }
}
