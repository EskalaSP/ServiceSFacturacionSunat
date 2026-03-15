<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateCreditNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCreditNoteRequest;
use App\Http\Resources\Api\V1\CreditNoteResource;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\CachesPdf;
use App\Jobs\SendDocumentToSunat;
use App\Models\CreditNote;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    use ApiResponse, CachesPdf;

    public function store(StoreCreditNoteRequest $request, CreateCreditNoteAction $action): JsonResponse
    {
        $tenant = $request->get('tenant');

        try {
            $creditNote = $action->execute($tenant, $request->validated());

            return $this->created(new CreditNoteResource($creditNote), 'Nota de crédito creada y encolada para envío a SUNAT.');
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

        if ($request->has('client_num_doc')) {
            $query->where('client_num_doc', $request->input('client_num_doc'));
        }

        if ($request->has('sucursal_id')) {
            $sucursal = \App\Models\Sucursal::find($request->input('sucursal_id'));
            if ($sucursal) {
                $query->where('cod_local', $sucursal->cod_local);
            }
        }

        if ($request->has('tipo_moneda')) {
            $query->where('tipo_moneda', $request->input('tipo_moneda'));
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

    public function resend(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $creditNote = CreditNote::forTenant($tenant->id)->findOrFail($id);

        if ($creditNote->sunat_status === 'aceptado') {
            return $this->error('Esta nota de crédito ya fue aceptada por SUNAT.', 422);
        }

        $creditNote->update([
            'sunat_status' => 'pendiente',
            'sunat_code' => null,
            'sunat_description' => null,
        ]);

        SendDocumentToSunat::dispatch(CreditNote::class, $creditNote->id);

        return $this->success(
            new CreditNoteResource($creditNote->fresh()),
            'Nota de crédito reenviada a SUNAT.'
        );
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

        $content = $this->getCachedPdfContent($creditNote, $formatStr);

        if (! $content) {
            $content = app(PdfGeneratorService::class)->generate($creditNote, $tenant, $format);
            $this->cachePdfContent($creditNote, $formatStr, $content);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$creditNote->numero_completo}.pdf\"",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
