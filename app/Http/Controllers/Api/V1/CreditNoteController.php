<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateCreditNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCreditNoteRequest;
use App\Http\Resources\Api\V1\CreditNoteResource;
use App\Http\Traits\ApiResponse;
use App\Models\CreditNote;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    use ApiResponse;

    public function store(StoreCreditNoteRequest $request, CreateCreditNoteAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');
        $async = $request->boolean('async', false);

        try {
            $creditNote = $action->execute($tenant, $request->validated(), $async);

            $message = $async
                ? 'Nota de crédito creada y encolada para envío a SUNAT.'
                : 'Nota de crédito creada y enviada a SUNAT.';

            return $this->created(new CreditNoteResource($creditNote), $message);
        } catch (\Throwable $e) {
            return $this->error('Error al crear nota de crédito: ' . $e->getMessage(), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = CreditNote::with('items')
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

        $creditNotes = $query->paginate($request->integer('per_page', 15));

        return $this->success([
            'data' => CreditNoteResource::collection($creditNotes),
            'pagination' => [
                'current_page' => $creditNotes->currentPage(),
                'last_page' => $creditNotes->lastPage(),
                'per_page' => $creditNotes->perPage(),
                'total' => $creditNotes->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::with('items')->forTenant($tenant->id)->findOrFail($id);

        return $this->success(new CreditNoteResource($creditNote));
    }

    public function xml(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getXmlContent($creditNote);

        if (! $content) {
            return $this->error('XML no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$creditNote->numero_completo}.xml\"",
        ]);
    }

    public function cdr(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::forTenant($tenant->id)->findOrFail($id);

        $storage = new DocumentStorageService();
        $content = $storage->getCdrContent($creditNote);

        if (! $content) {
            return $this->error('CDR no disponible', 404);
        }

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"R-{$creditNote->numero_completo}.zip\"",
        ]);
    }

    public function pdf(Request $request, int $id): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::with('items')->forTenant($tenant->id)->findOrFail($id);
        $formatStr = $request->input('format', config('pdf.default_format', 'a4'));

        try {
            $format = PdfFormatConfig::from($formatStr);
        } catch (\ValueError) {
            return $this->error('Formato inválido. Opciones: a4, a5, ticket-80, ticket-58', 422);
        }

        if (! $request->has('format') && $creditNote->pdf_path) {
            $storage = new DocumentStorageService();
            $content = $storage->getPdfContent($creditNote);
            if ($content) {
                return response($content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"{$creditNote->numero_completo}.pdf\"",
                ]);
            }
        }

        $content = app(PdfGeneratorService::class)->generate($creditNote, $tenant, $format);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$creditNote->numero_completo}.pdf\"",
        ]);
    }
}
