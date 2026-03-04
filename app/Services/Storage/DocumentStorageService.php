<?php

namespace App\Services\Storage;

use App\Models\DispatchGuide;
use App\Models\Document;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

class DocumentStorageService
{
    /**
     * Estructura: {ruc}/{cod_local}/{YYYY-MM-DD}/{tipo}/archivo
     *
     * Ejemplo: 20123456789/0000/2026-03-04/xml/F001-1.xml
     *          20123456789/0000/2026-03-04/cdr/R-F001-1.zip
     *          20123456789/0000/2026-03-04/pdf/F001-1.pdf
     */
    public function storeXml(Document|DispatchGuide $document, Tenant $tenant, string $xmlContent): string
    {
        $path = $this->buildPath($tenant, $document, 'xml');
        $filename = $document->numero_completo.'.xml';
        $fullPath = $path.'/'.$filename;

        Storage::disk('public')->put($fullPath, $xmlContent);

        $document->update(['xml_path' => $fullPath]);

        return $fullPath;
    }

    public function storeCdr(Document|DispatchGuide $document, Tenant $tenant, string $cdrContent): string
    {
        $path = $this->buildPath($tenant, $document, 'cdr');
        $filename = 'R-'.$document->numero_completo.'.zip';
        $fullPath = $path.'/'.$filename;

        Storage::disk('public')->put($fullPath, $cdrContent);

        $document->update(['cdr_path' => $fullPath]);

        return $fullPath;
    }

    public function storePdf(Document|DispatchGuide $document, Tenant $tenant, string $pdfContent): string
    {
        $path = $this->buildPath($tenant, $document, 'pdf');
        $filename = $document->numero_completo.'.pdf';
        $fullPath = $path.'/'.$filename;

        Storage::disk('public')->put($fullPath, $pdfContent);

        $document->update(['pdf_path' => $fullPath]);

        return $fullPath;
    }

    public function getXmlContent(Document|DispatchGuide $document): ?string
    {
        if ($document->xml_path && Storage::disk('public')->exists($document->xml_path)) {
            return Storage::disk('public')->get($document->xml_path);
        }

        return $document->xml_content;
    }

    public function getCdrContent(Document|DispatchGuide $document): ?string
    {
        if ($document->cdr_path && Storage::disk('public')->exists($document->cdr_path)) {
            return Storage::disk('public')->get($document->cdr_path);
        }

        return $document->cdr_content;
    }

    public function getPdfContent(Document|DispatchGuide $document): ?string
    {
        if ($document->pdf_path && Storage::disk('public')->exists($document->pdf_path)) {
            return Storage::disk('public')->get($document->pdf_path);
        }

        return null;
    }

    public function getXmlUrl(Document|DispatchGuide $document): ?string
    {
        if ($document->xml_path) {
            return Storage::disk('public')->url($document->xml_path);
        }

        return null;
    }

    public function getCdrUrl(Document|DispatchGuide $document): ?string
    {
        if ($document->cdr_path) {
            return Storage::disk('public')->url($document->cdr_path);
        }

        return null;
    }

    public function getPdfUrl(Document|DispatchGuide $document): ?string
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

    private function buildPath(Tenant $tenant, Document|DispatchGuide $document, string $type): string
    {
        $codLocal = $document->cod_local ?? '0000';
        $fecha = $document->fecha_emision instanceof \DateTimeInterface
            ? $document->fecha_emision->format('Y-m-d')
            : $document->fecha_emision;

        return $tenant->ruc.'/'.$codLocal.'/'.$fecha.'/'.$type;
    }
}
