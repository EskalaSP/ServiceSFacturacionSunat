<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateBoletaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBoletaRequest;
use App\Http\Resources\Api\V1\BoletaResource;
use App\Http\Traits\ApiResponse;
use App\Models\Boleta;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoletaController extends Controller
{
    use ApiResponse;

    public function store(StoreBoletaRequest $request, CreateBoletaAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $async = $request->boolean('async', false);
        $soloRegistro = $request->boolean('solo_registro', false);

        try {
            $boleta = $action->execute($tenant, $request->validated(), $async, $soloRegistro);

            $message = match (true) {
                $soloRegistro => 'Boleta registrada. Pendiente de envío vía resumen diario.',
                $async => 'Boleta creada y encolada para envío a SUNAT.',
                default => 'Boleta creada y enviada a SUNAT.',
            };

            return $this->created(new BoletaResource($boleta), $message);
        } catch (\Throwable $e) {
            return $this->error('Error al crear boleta: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Boleta::with('items')
            ->forTenant($tenant->id)
            ->orderByDesc('created_at');

        if ($request->has('sunat_status')) {
            $query->status($request->input('sunat_status'));
        }

        if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
            $query->fechas($request->input('fecha_desde'), $request->input('fecha_hasta'));
        }

        if ($request->has('serie')) {
            $query->where('serie', $request->input('serie'));
        }

        if ($request->has('client_num_doc')) {
            $query->where('client_num_doc', $request->input('client_num_doc'));
        }

        $boletas = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => BoletaResource::collection($boletas),
            'pagination' => [
                'current_page' => $boletas->currentPage(),
                'last_page' => $boletas->lastPage(),
                'per_page' => $boletas->perPage(),
                'total' => $boletas->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::with('items')->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new BoletaResource($boleta));
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($boleta);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$boleta->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($boleta);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$boleta->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $boleta = Boleta::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        if (! $request->has('format') && $boleta->pdf_path) {
            $storage = new DocumentStorageService();
            $content = $storage->getPdfContent($boleta);
            if ($content) {
                return response($content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"{$boleta->numero_completo}.pdf\"",
                ]);
            }
        }

        $content = app(PdfGeneratorService::class)->generate($boleta, $tenant, $format);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$boleta->numero_completo}.pdf\"",
        ]);
    }
}
