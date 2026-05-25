<?php

namespace App\Http\Traits;

use App\Services\Storage\DocumentStorageService;
use Illuminate\Database\Eloquent\Model;

trait CachesPdf
{
    protected function getCachedPdfContent(Model $document, string $formatStr): ?string
    {
        // Formato predeterminado: usar pdf_path almacenado
        $defaultFormat = config('pdf.default_format', 'a4');
        if ($formatStr === $defaultFormat && $document->pdf_path) {
            $content = (new DocumentStorageService())->getPdfContent($document);
            if ($content) {
                return $content;
            }
        }

        // Cualquier formato: verificar caché en disco (TTL de 1 hora)
        $cachePath = $this->buildPdfCachePath($document, $formatStr);
        if (file_exists($cachePath) && filemtime($cachePath) > time() - 3600) {
            return file_get_contents($cachePath);
        }

        return null;
    }

    protected function cachePdfContent(Model $document, string $formatStr, string $content): void
    {
        $cachePath = $this->buildPdfCachePath($document, $formatStr);
        $dir = dirname($cachePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($cachePath, $content);
    }

    private function buildPdfCachePath(Model $document, string $format): string
    {
        $tenantId = $document->tenant_id ?? 0;
        $table = $document->getTable();

        return storage_path("app/pdf-cache/{$tenantId}/{$table}_{$document->id}_{$format}.pdf");
    }
}
