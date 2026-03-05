<?php

namespace App\Services\Pdf;

use App\Models\Tenant;
use App\Services\Storage\DocumentStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;

class PdfGeneratorService
{
    public function __construct(
        private DocumentDataMapper $mapper,
        private DocumentStorageService $storage,
    ) {}

    public function generate(Model $document, Tenant $tenant, PdfFormatConfig $format = null): string
    {
        $format ??= PdfFormatConfig::from(config('pdf.default_format', 'a4'));

        $data = $this->mapper->map($document, $tenant);
        $data['format'] = $format;
        $data['is_ticket'] = $format->isTicket();
        $data['sections'] = DocumentTypeConfig::sections($data['tipo_documento']);

        $pdf = Pdf::loadView($format->viewName(), $data)
            ->setPaper($format->paperSize(), $format->orientation())
            ->setOption('isRemoteEnabled', true);

        return $pdf->output();
    }

    public function generateAndStore(Model $document, Tenant $tenant, PdfFormatConfig $format = null): string
    {
        $format ??= PdfFormatConfig::from(config('pdf.store_format', 'a4'));

        $pdfContent = $this->generate($document, $tenant, $format);
        $this->storage->storePdf($document, $tenant, $pdfContent);

        return $pdfContent;
    }
}
