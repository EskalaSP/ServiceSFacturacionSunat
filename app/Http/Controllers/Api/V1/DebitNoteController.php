<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateDebitNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDebitNoteRequest;
use App\Http\Resources\Api\V1\DebitNoteResource;
use App\Http\Traits\ApiResponse;
use App\Models\DebitNote;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebitNoteController extends Controller
{
    use ApiResponse;

    public function store(StoreDebitNoteRequest $request, CreateDebitNoteAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $debitNote = $action->execute($tenant, $request->validated());

            return $this->created(new DebitNoteResource($debitNote), 'Nota de débito creada y encolada para envío a SUNAT.');
        } catch (\Throwable $e) {
            return $this->error('Error al crear nota de débito: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = DebitNote::with('items')
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

        $debitNotes = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => DebitNoteResource::collection($debitNotes),
            'pagination' => [
                'current_page' => $debitNotes->currentPage(),
                'last_page' => $debitNotes->lastPage(),
                'per_page' => $debitNotes->perPage(),
                'total' => $debitNotes->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::with('items')->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new DebitNoteResource($debitNote));
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($debitNote);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$debitNote->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($debitNote);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$debitNote->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $debitNote = DebitNote::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        if (! $request->has('format') && $debitNote->pdf_path) {
            $storage = new DocumentStorageService();
            $content = $storage->getPdfContent($debitNote);
            if ($content) {
                return response($content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"{$debitNote->numero_completo}.pdf\"",
                ]);
            }
        }

        $content = app(PdfGeneratorService::class)->generate($debitNote, $tenant, $format);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$debitNote->numero_completo}.pdf\"",
        ]);
    }
}
