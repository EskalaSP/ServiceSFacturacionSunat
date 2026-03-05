<?php

namespace App\Services\Storage;

use App\Contracts\Documentable;
use App\Models\DispatchGuide;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentStorageService
{
    /**
     * Estructura: {ruc}/{cod_local}/{YYYY-MM-DD}/{comprobante}/{tipo}/archivo
     *
     * Ejemplo: 20123456789/0000/2026-03-04/facturas/xml/F001-1.xml
     *          20123456789/0000/2026-03-04/facturas/cdr/R-F001-1.zip
     *          20123456789/0000/2026-03-04/facturas/pdf/F001-1.pdf
     *          20123456789/0000/2026-03-04/boletas/xml/B001-1.xml
     *          20123456789/0000/2026-03-04/notas_credito/xml/FC01-1.xml
     *          20123456789/0000/2026-03-04/notas_debito/xml/FD01-1.xml
     *          20123456789/0000/2026-03-04/guias/xml/T001-1.xml
     *
     * Acepta cualquier modelo Documentable (Invoice, Boleta, CreditNote, DebitNote) o DispatchGuide.
     */
    public function storeXml(Model $document, Tenant $tenant, string $xmlContent): string
    {
        $path = $this->buildPath($tenant, $document, 'xml');
        $filename = $document->numero_completo.'.xml';
        $fullPath = $path.'/'.$filename;

        Storage::disk('public')->put($fullPath, $xmlContent);

        $document->update(['xml_path' => $fullPath]);

        return $fullPath;
    }

    public function storeCdr(Model $document, Tenant $tenant, string $cdrContent): string
    {
        $path = $this->buildPath($tenant, $document, 'cdr');
        $filename = 'R-'.$document->numero_completo.'.zip';
        $fullPath = $path.'/'.$filename;

        Storage::disk('public')->put($fullPath, $cdrContent);

        $document->update(['cdr_path' => $fullPath]);

        return $fullPath;
    }

    public function storePdf(Model $document, Tenant $tenant, string $pdfContent): string
    {
        $path = $this->buildPath($tenant, $document, 'pdf');
        $filename = $document->numero_completo.'.pdf';
        $fullPath = $path.'/'.$filename;

        Storage::disk('public')->put($fullPath, $pdfContent);

        $document->update(['pdf_path' => $fullPath]);

        return $fullPath;
    }

    public function getXmlContent(Model $document): ?string
    {
        if ($document->xml_path && Storage::disk('public')->exists($document->xml_path)) {
            return Storage::disk('public')->get($document->xml_path);
        }

        return $document->xml_content ?? null;
    }

    public function getCdrContent(Model $document): ?string
    {
        if ($document->cdr_path && Storage::disk('public')->exists($document->cdr_path)) {
            return Storage::disk('public')->get($document->cdr_path);
        }

        return $document->cdr_content ?? null;
    }

    public function getPdfContent(Model $document): ?string
    {
        if ($document->pdf_path && Storage::disk('public')->exists($document->pdf_path)) {
            return Storage::disk('public')->get($document->pdf_path);
        }

        return null;
    }

    public function getXmlUrl(Model $document): ?string
    {
        if ($document->xml_path) {
            return Storage::disk('public')->url($document->xml_path);
        }

        return null;
    }

    public function getCdrUrl(Model $document): ?string
    {
        if ($document->cdr_path) {
            return Storage::disk('public')->url($document->cdr_path);
        }

        return null;
    }

    public function getPdfUrl(Model $document): ?string
    {
        if ($document->pdf_path) {
            return Storage::disk('public')->url($document->pdf_path);
        }

        return null;
    }

    /**
     * Guardar certificado en storage privado.
     * Ruta: certificates/{ruc}/cert.pem
     */
    public function storeCertificate(Tenant $tenant, string $certContent, string $filename = 'cert.pem'): string
    {
        $path = 'certificates/'.$tenant->ruc.'/'.$filename;

        Storage::disk('local')->put($path, $certContent);

        return Storage::disk('local')->path($path);
    }

    public function getCertificatePath(Tenant $tenant, string $filename = 'cert.pem'): ?string
    {
        $path = 'certificates/'.$tenant->ruc.'/'.$filename;

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        return null;
    }

    private function buildPath(Tenant $tenant, Model $document, string $type): string
    {
        $codLocal = $document->cod_local ?? '0000';
        $fecha = $document->fecha_emision instanceof \DateTimeInterface
            ? $document->fecha_emision->format('Y-m-d')
            : $document->fecha_emision;

        $carpeta = $this->resolveDocumentFolder($document);

        return $tenant->ruc.'/'.$codLocal.'/'.$fecha.'/'.$carpeta.'/'.$type;
    }

    private function resolveDocumentFolder(Model $document): string
    {
        if ($document instanceof DispatchGuide) {
            return 'guias';
        }

        if ($document instanceof Documentable) {
            return match ($document->getTipoDocumento()) {
                '01' => 'facturas',
                '03' => 'boletas',
                '07' => 'notas_credito',
                '08' => 'notas_debito',
                default => 'otros',
            };
        }

        return 'otros';
    }
}
