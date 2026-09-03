<?php

namespace App\Console\Commands;

use App\Models\Boleta;
use App\Models\Invoice;
use App\Models\Summary;
use App\Models\Tenant;
use App\Models\VoidedDocument;
use App\Services\SunatCpeConsultaService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clasifica el campo `ambiente` (prueba | produccion) de cada comprobante
 * consultando su estado real en SUNAT (Consulta Integrada CPE) y, cuando
 * corresponde, corrige el `sunat_status` (p. ej. revierte un "anulado"
 * que en SUNAT figura como ACEPTADO).
 */
class SunatSyncAmbiente extends Command
{
    protected $signature = 'sunat:sync-ambiente
        {--dry-run : Solo muestra los cambios sin escribir en la base de datos}
        {--tenant= : Limitar a un tenant específico (id)}
        {--limit=0 : Máximo de documentos a procesar por tipo (0 = sin límite)}
        {--sleep=1 : Segundos de espera entre cada consulta a SUNAT}
        {--only-production : Procesar solo documentos ya marcados como produccion}';

    protected $description = 'Sincroniza el ambiente (prueba/produccion) de los comprobantes consultando SUNAT (CPE).';

    /** Modelos de documentos que se consultan en SUNAT (CPE solo admite factura y boleta). */
    private const DOCUMENT_MODELS = [
        Invoice::class,
        Boleta::class,
    ];

    private int $procesados = 0;
    private int $cambiados = 0;
    private int $revisionManual = 0;
    private int $errores = 0;

    public function handle(SunatCpeConsultaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $sleep = (float) $this->option('sleep');
        $onlyProduction = (bool) $this->option('only-production');

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: no se escribirá nada en la base de datos.');
        }

        foreach (self::DOCUMENT_MODELS as $modelClass) {
            $this->line("\n<info>=== Procesando ".class_basename($modelClass).' ===</info>');

            $query = $modelClass::query()
                ->with('tenant')
                ->whereHas('tenant', function ($q) {
                    $q->whereNotNull('client_id')->whereNotNull('client_secret');
                });

            if ($tenantId = $this->option('tenant')) {
                $query->where('tenant_id', $tenantId);
            }

            if ($onlyProduction) {
                $query->where('ambiente', 'produccion');
            }

            if ($limit > 0) {
                $query->limit($limit);
            }

            $query->orderBy('id')->chunkById(100, function ($documentos) use ($service, $dryRun, $sleep) {
                foreach ($documentos as $doc) {
                    $this->procesarDocumento($service, $doc, $dryRun);

                    if ($sleep > 0) {
                        usleep((int) ($sleep * 1_000_000));
                    }
                }
            });
        }

        $this->newLine();
        $this->info("Procesados: {$this->procesados}");
        $this->info("Actualizados: {$this->cambiados}");
        $this->warn("Revisión manual (NO AUTORIZADO): {$this->revisionManual}");
        if ($this->errores > 0) {
            $this->error("Errores: {$this->errores}");
        }

        return self::SUCCESS;
    }

    private function procesarDocumento(SunatCpeConsultaService $service, Model $doc, bool $dryRun): void
    {
        $this->procesados++;
        $tenant = $doc->tenant;

        if (! $tenant instanceof Tenant) {
            return;
        }

        $numero = $doc->serie.'-'.$doc->correlativo;
        $empresa = trim(($tenant->ruc ?? '').' '.($tenant->razon_social ?? ''));
        $etiqueta = "[{$empresa}] {$numero}";

        try {
            $filter = [
                'ruc_emisor' => $tenant->ruc,
                'tipo_doc' => $doc->getTipoDocumento(),
                'serie' => $doc->serie,
                'correlativo' => (string) $doc->correlativo,
                'fecha_emision' => Carbon::parse($doc->fecha_emision)->format('d/m/Y'),
                'monto' => (float) $doc->mto_imp_venta,
            ];

            $res = $service->consultar($tenant, $filter);
        } catch (\Throwable $e) {
            $this->errores++;
            $this->line("  <fg=red>ERROR</> {$numero}: {$e->getMessage()}");
            Log::error('sync-ambiente: fallo consulta CPE', [
                'documento' => $doc->getKey(),
                'tipo' => class_basename($doc),
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (empty($res['encontrado'])) {
            // SUNAT no pudo resolver la consulta (error de servicio, credenciales, etc.).
            $this->line("  <fg=yellow>SIN RESPUESTA</> {$etiqueta}: ".($res['mensaje'] ?? 'desconocido'));

            return;
        }

        $estadoCp = (string) ($res['estado_cp'] ?? '');
        $decision = $this->decidir($doc, $estadoCp);

        if ($decision === null) {
            // Caso ambiguo (NO AUTORIZADO): no se toca, se registra para revisión.
            $this->revisionManual++;
            $this->line("  <fg=magenta>REVISIÓN MANUAL</> {$etiqueta}: estado_cp={$estadoCp} ({$res['estado_cp_descripcion']})");
            Log::warning('sync-ambiente: comprobante NO AUTORIZADO, requiere revisión manual', [
                'documento' => $doc->getKey(),
                'tipo' => class_basename($doc),
                'numero' => $numero,
                'tenant_id' => $tenant->id,
                'respuesta' => $res,
            ]);

            return;
        }

        if (empty($decision['changes'])) {
            $this->line("  <fg=gray>OK</> {$etiqueta}: sin cambios ({$res['estado_cp_descripcion']})");
        } else {
            $this->cambiados++;
            $resumen = collect($decision['changes'])
                ->map(fn ($v, $k) => "{$k}: ".($doc->getOriginal($k) ?? 'null').' → '.$v)
                ->implode(', ');
            $this->line("  <fg=green>CAMBIO</> {$etiqueta}: {$resumen}");

            if (! $dryRun) {
                DB::transaction(function () use ($doc, $decision) {
                    $doc->forceFill($decision['changes'])->save();
                });
            }
        }

        // Propaga el ambiente al resumen diario (boletas) o a la RA (facturas).
        $this->cascadaAmbiente($doc, $decision['ambiente'], $dryRun);
    }

    /**
     * Propaga el ambiente del documento a su resumen diario (boleta) o a su
     * comunicación de baja / RA (factura), para que coincidan con el documento.
     */
    private function cascadaAmbiente(Model $doc, string $ambiente, bool $dryRun): void
    {
        if ($doc instanceof Boleta) {
            $resumenes = Summary::where('tenant_id', $doc->tenant_id)
                ->whereJsonContains('document_ids', $doc->id)
                ->where('ambiente', '!=', $ambiente)
                ->get();

            foreach ($resumenes as $summary) {
                $this->line("    ↳ Resumen {$summary->identifier}: ambiente {$summary->ambiente} → {$ambiente}");
                if (! $dryRun) {
                    $summary->update(['ambiente' => $ambiente]);
                }
            }

            return;
        }

        if ($doc instanceof Invoice) {
            $ras = VoidedDocument::where('tenant_id', $doc->tenant_id)
                ->where('ambiente', '!=', $ambiente)
                ->get();

            foreach ($ras as $ra) {
                $coincide = collect($ra->detalles ?? [])->contains(
                    fn ($det) => ($det['tipo_documento'] ?? null) === $doc->getTipoDocumento()
                        && ($det['serie'] ?? null) === $doc->serie
                        && (string) ($det['correlativo'] ?? '') === (string) $doc->correlativo
                );

                if ($coincide) {
                    $this->line("    ↳ RA {$ra->identifier}: ambiente {$ra->ambiente} → {$ambiente}");
                    if (! $dryRun) {
                        $ra->update(['ambiente' => $ambiente]);
                    }
                }
            }
        }
    }

    /**
     * Devuelve el ambiente destino y los cambios a aplicar según el estado en SUNAT.
     * Retorna null cuando el caso es ambiguo y debe revisarse manualmente.
     *
     * @return array{ambiente: string, changes: array<string,string>}|null
     */
    private function decidir(Model $doc, string $estadoCp): ?array
    {
        return match ($estadoCp) {
            // NO EXISTE en SUNAT: nunca fue aceptado en producción real → prueba.
            '0' => (function () use ($doc) {
                $changes = [];
                if ($doc->ambiente !== 'prueba') {
                    $changes['ambiente'] = 'prueba';
                }

                return ['ambiente' => 'prueba', 'changes' => $changes];
            })(),

            // ACEPTADO o AUTORIZADO en SUNAT: es producción real.
            '1', '3' => (function () use ($doc) {
                $changes = [];
                if ($doc->ambiente !== 'produccion') {
                    $changes['ambiente'] = 'produccion';
                }
                // Si estaba marcado "anulado" pero SUNAT lo da por vigente,
                // la baja no se completó: se revierte a aceptado.
                if ($doc->sunat_status === 'anulado') {
                    $changes['sunat_status'] = 'aceptado';
                }

                return ['ambiente' => 'produccion', 'changes' => $changes];
            })(),

            // ANULADO en SUNAT: es producción real y la baja sí se aplicó.
            '2' => (function () use ($doc) {
                $changes = [];
                if ($doc->ambiente !== 'produccion') {
                    $changes['ambiente'] = 'produccion';
                }
                if ($doc->sunat_status !== 'anulado') {
                    $changes['sunat_status'] = 'anulado';
                }

                return ['ambiente' => 'produccion', 'changes' => $changes];
            })(),

            // NO AUTORIZADO (4) o cualquier otro: ambiguo → revisión manual.
            default => null,
        };
    }
}
