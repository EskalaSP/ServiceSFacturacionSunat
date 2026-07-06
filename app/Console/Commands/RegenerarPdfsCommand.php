<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\Perception;
use App\Models\Retention;
use App\Services\Pdf\PdfGeneratorService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Regenera los PDF ALMACENADOS (pdf_path) de los documentos emitidos.
 *
 * Necesario cuando se cambia una plantilla Blade del PDF: el formato default
 * (a4) se sirve desde el archivo guardado en emisión, así que un cambio de
 * plantilla NO se refleja en documentos viejos hasta regenerarlos.
 * También limpia el caché en disco de los formatos no-default (pdf-cache).
 *
 * Uso:
 *   php artisan pdf:regenerar               # todos los documentos con pdf_path
 *   php artisan pdf:regenerar --tenant=5    # solo un tenant
 *   php artisan pdf:regenerar --tipo=invoice
 */
class RegenerarPdfsCommand extends Command
{
    protected $signature = 'pdf:regenerar
        {--tenant= : Regenerar solo los documentos de este tenant_id}
        {--tipo= : Solo un tipo: invoice, boleta, credit-note, debit-note, dispatch-guide, retention, perception, internal}';

    protected $description = 'Regenera los PDF almacenados de los documentos (tras cambiar plantillas)';

    /** @var array<string, class-string<Model>> */
    private array $modelos = [
        'invoice'        => Invoice::class,
        'boleta'         => Boleta::class,
        'credit-note'    => CreditNote::class,
        'debit-note'     => DebitNote::class,
        'dispatch-guide' => DispatchGuide::class,
        'retention'      => Retention::class,
        'perception'     => Perception::class,
        'internal'       => InternalDocument::class,
    ];

    public function handle(PdfGeneratorService $pdf, DocumentStorageService $storage): int
    {
        // 1. Limpiar el caché en disco de los formatos no-default (a5, tickets).
        $cacheDir = storage_path('app/pdf-cache');
        if (File::isDirectory($cacheDir)) {
            File::deleteDirectory($cacheDir);
            $this->info('Caché de PDF en disco limpiado (pdf-cache/).');
        }

        $tipoFiltro = $this->option('tipo');
        $tenantId   = $this->option('tenant');

        $modelos = $tipoFiltro
            ? array_intersect_key($this->modelos, [$tipoFiltro => true])
            : $this->modelos;

        if (empty($modelos)) {
            $this->error("Tipo inválido. Opciones: " . implode(', ', array_keys($this->modelos)));
            return self::FAILURE;
        }

        $ok = 0;
        $errores = 0;

        foreach ($modelos as $nombre => $clase) {
            // Guías de remisión (09/31) no tienen 'items' — eager-load condicional.
            $relaciones = method_exists(new $clase(), 'items')
                ? ['items', 'tenant']
                : ['tenant'];

            $query = $clase::query()
                ->whereNotNull('pdf_path')
                ->with($relaciones);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $total = $query->count();
            if ($total === 0) {
                continue;
            }

            $this->line("Regenerando {$total} {$nombre}(s)...");
            $bar = $this->output->createProgressBar($total);

            $query->chunkById(50, function ($docs) use ($pdf, $storage, &$ok, &$errores, $bar) {
                foreach ($docs as $doc) {
                    try {
                        if (! $doc->tenant) {
                            $errores++;
                            $bar->advance();
                            continue;
                        }
                        $contenido = $pdf->generate($doc, $doc->tenant);
                        $storage->storePdf($doc, $doc->tenant, $contenido);
                        $ok++;
                    } catch (Throwable $e) {
                        $errores++;
                        $this->newLine();
                        $this->warn("  Error en {$doc->serie}-{$doc->correlativo}: {$e->getMessage()}");
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine(2);
        }

        $this->info("Listo. Regenerados: {$ok}. Errores: {$errores}.");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
