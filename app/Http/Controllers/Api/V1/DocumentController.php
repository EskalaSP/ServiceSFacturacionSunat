<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Http\Traits\ApiResponse;
use App\Models\Document;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    use ApiResponse;

    public function store(StoreDocumentRequest $request, CreateDocumentAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $async = $request->boolean('async', false);

        try {
            $document = $action->execute($tenant, $request->validated(), $async);

            return $this->created(new DocumentResource($document), $async
                ? 'Documento creado y encolado para envío a SUNAT.'
                : 'Documento creado y enviado a SUNAT.');
        } catch (\Throwable $e) {
            return $this->error('Error al crear documento: '.$e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Document::with('items')
            ->forTenant($tenant->id)
            ->orderByDesc('created_at');

        if ($request->has('tipo_documento')) {
            $query->tipo($request->input('tipo_documento'));
        }

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

        $documents = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => DocumentResource::collection($documents),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');

        $document = Document::with('items')
            ->forTenant($tenant->id)
            ->findOrFail($id);

        return $this->success(new DocumentResource($document));
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $document = Document::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($document);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$document->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $document = Document::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($document);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$document->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $document = Document::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getPdfContent($document);

        if (! $content) {
            return $this->error('PDF no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$document->numero_completo}.pdf\"",
        ]);
    }
}
