<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearPdfCache extends Command
{
    protected $signature = 'pdf-cache:clear
                            {--tenant= : ID del tenant (opcional; si se omite, limpia TODOS)}
                            {--keep-paths : No vacía pdf_path — solo borra el caché temporal por formato}';

    protected $description = 'Limpia el caché de PDFs generados. Fuerza regeneración con los datos actuales (útil tras cambios de IGV, logo, mensajes, etc.)';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $keepPaths = (bool) $this->option('keep-paths');

        // 1. Borrar caché temporal por formato (app/pdf-cache/*)
        $cacheRoot = storage_path('app/pdf-cache');

        if (is_dir($cacheRoot)) {
            if ($tenantId) {
                $tenantDir = "{$cacheRoot}/{$tenantId}";
                if (is_dir($tenantDir)) {
                    File::deleteDirectory($tenantDir);
                    $this->info("✔ Caché por formato del tenant {$tenantId} borrado");
                } else {
                    $this->line("· Tenant {$tenantId} no tenía caché por formato");
                }
            } else {
                File::cleanDirectory($cacheRoot);
                $this->info('✔ Caché por formato (todos los tenants) borrado');
            }
        } else {
            $this->line('· Directorio de caché por formato no existía');
        }

        // 2. Vaciar pdf_path de los documentos → obliga a regenerar en el próximo GET /pdf
        if (! $keepPaths) {
            $models = [
                Invoice::class => 'facturas',
                Boleta::class => 'boletas',
                CreditNote::class => 'notas de crédito',
                DebitNote::class => 'notas de débito',
                DispatchGuide::class => 'guías de remisión',
            ];

            $totalActualizados = 0;

            foreach ($models as $model => $label) {
                $query = $model::query()->whereNotNull('pdf_path');
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                $count = (clone $query)->count();

                if ($count === 0) {
                    $this->line("· {$label}: nada que limpiar");

                    continue;
                }

                $query->update(['pdf_path' => null]);
                $this->info("✔ {$label}: {$count} pdf_path vaciados");
                $totalActualizados += $count;
            }

            $this->newLine();
            $this->info("Total documentos marcados para regenerar: {$totalActualizados}");
        }

        return self::SUCCESS;
    }
}
