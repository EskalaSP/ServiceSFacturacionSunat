<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\Invoice;
use App\Models\Perception;
use App\Models\Retention;
use App\Services\Pdf\PdfFormatConfig;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\Request;

/**
 * Genera y sirve el PDF de un comprobante en el formato solicitado (a4, a5,
 * ticket-80, ticket-58). Reutiliza el mismo PdfGeneratorService que la API.
 * Se usa para la vista previa en modal tras emitir.
 */
class DocumentoPdfController extends Controller
{
    public function show(Request $request, string $tipo, int $id, PdfGeneratorService $pdf): \Illuminate\Http\Response
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        $doc = $this->modelPara($tipo)::forTenant($tenant->id)->findOrFail($id);

        try {
            $format = PdfFormatConfig::from($request->input('format', config('pdf.default_format', 'a4')));
        } catch (\ValueError) {
            $format = PdfFormatConfig::A4;
        }

        $content = $pdf->generate($doc, $tenant, $format);
        $numero = $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$numero}.pdf\"",
            'Cache-Control' => 'private, max-age=120',
        ]);
    }

    /** @return class-string */
    private function modelPara(string $tipo): string
    {
        return match ($tipo) {
            '03' => Boleta::class,
            '07' => CreditNote::class,
            '08' => DebitNote::class,
            '09', '31' => DispatchGuide::class,
            '20' => Retention::class,
            '40' => Perception::class,
            default => Invoice::class,
        };
    }
}
