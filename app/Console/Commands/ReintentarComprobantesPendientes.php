<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendDocumentToSunat;
use App\Models\Boleta;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Recuperación automática de comprobantes atascados.
 *
 * Reencola a SUNAT las facturas/boletas que quedaron en `pendiente` o `enviado`
 * sin llegar a un estado final. Cubre dos casos:
 *   1. Agotaron los 20 reintentos del job (SUNAT caído > ~15h).
 *   2. Se crearon cuando el queue worker no estaba corriendo (job nunca ejecutó).
 *
 * NO toca los `rechazado`: un rechazo de SUNAT es un error de datos permanente
 * y reenviarlo sin corregir da el mismo rechazo. Esos requieren PUT /facturas/{id}.
 *
 * Diseñado para correr cada pocos minutos vía scheduler (withoutOverlapping).
 * El propio job respeta el circuit breaker, así que reencolar es seguro aunque
 * SUNAT siga caído (los jobs se auto-espacian).
 */
class ReintentarComprobantesPendientes extends Command
{
    protected $signature = 'sunat:reintentar-pendientes
        {--minutos=15 : Antigüedad mínima (min) en el estado antes de reintentar — evita pisar jobs en curso}
        {--dias=7 : No reintentar comprobantes creados hace más de estos días}
        {--estados=pendiente,enviado : Estados a reintentar (separados por coma)}';

    protected $description = 'Reencola a SUNAT los comprobantes atascados en pendiente/enviado (recuperación automática).';

    public function handle(): int
    {
        $minutos = max(1, (int) $this->option('minutos'));
        $dias    = max(1, (int) $this->option('dias'));
        $estados = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('estados')))));

        // Ventana de seguridad: solo tocamos documentos cuyo updated_at es más
        // viejo que N minutos, para no reencolar uno que el worker está
        // procesando en este momento (evita doble envío / correlativo duplicado).
        $limiteReciente = Carbon::now()->subMinutes($minutos);
        $limiteAntiguo  = Carbon::now()->subDays($dias);

        $total = 0;

        foreach ([Invoice::class, Boleta::class] as $modelClass) {
            $modelClass::query()
                ->whereIn('sunat_status', $estados)
                ->where('updated_at', '<=', $limiteReciente)
                ->where('created_at', '>=', $limiteAntiguo)
                ->orderBy('id')
                ->chunkById(200, function ($docs) use ($modelClass, &$total) {
                    foreach ($docs as $doc) {
                        SendDocumentToSunat::dispatch($modelClass, $doc->id);
                        $total++;
                    }
                });
        }

        if ($total > 0) {
            $this->info("Reencolados {$total} comprobante(s) a SUNAT.");
        }

        return self::SUCCESS;
    }
}
