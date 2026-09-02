<?php

namespace App\Services\Documents;

use App\Jobs\SendReversionToSunat;
use App\Jobs\SendVoidedToSunat;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\Perception;
use App\Models\Retention;
use App\Models\Tenant;
use App\Models\VoidedDocument;
use Illuminate\Support\Carbon;

/**
 * Lógica de la Comunicación de Baja (RA) reutilizada por la API y por el panel web.
 * Las boletas (03) NO se anulan por RA — van por resumen diario de anulación.
 */
class VoidedService
{
    /** @var array<string,class-string> */
    public const MODELOS = [
        '01' => Invoice::class,
        '03' => Boleta::class,
        '07' => CreditNote::class,
        '08' => DebitNote::class,
    ];

    /**
     * Crea la comunicación de baja y (opcional) la encola a SUNAT.
     *
     * @param  array<int,array<string,mixed>>  $detalles
     * @return array{ok:bool, errores?:array<int,string>, voided?:VoidedDocument}
     */
    public function crear(
        Tenant $tenant,
        string $fechaGeneracion,
        ?string $fechaComunicacion,
        array $detalles,
        bool $enviarAuto = true,
        ?string $motivo = null,
        ?int $userId = null,
    ): array {
        $fechaCom = $fechaComunicacion ?: now()->format('Y-m-d');

        foreach ($detalles as $detalle) {
            if (($detalle['tipo_documento'] ?? null) === '03') {
                return ['ok' => false, 'errores' => ['Las boletas no se anulan por comunicación de baja; usa el resumen diario de anulación.']];
            }
        }

        $errores = $this->validarDetalles($tenant->id, $detalles);
        if (! empty($errores)) {
            return ['ok' => false, 'errores' => $errores];
        }

        $fechaId = str_replace('-', '', $fechaCom);
        $lastCorrelativo = VoidedDocument::where('tenant_id', $tenant->id)
            ->where('fecha_comunicacion', $fechaCom)
            ->max('correlativo') ?? 0;

        $correlativo = str_pad((string) ((int) $lastCorrelativo + 1), 3, '0', STR_PAD_LEFT);
        $identifier = "RA-{$fechaId}-{$correlativo}";

        $voided = VoidedDocument::create([
            'tenant_id' => $tenant->id,
            'ambiente' => $tenant->environment === 'produccion' ? 'produccion' : 'prueba',
            'identifier' => $identifier,
            'correlativo' => $correlativo,
            'fecha_generacion' => $fechaGeneracion,
            'fecha_comunicacion' => $fechaCom,
            'total_documentos' => count($detalles),
            'detalles' => $detalles,
            'motivo' => $motivo ?? ($detalles[0]['motivo'] ?? null),
            'anulado_por' => $userId,
            'sunat_status' => 'pendiente',
        ]);

        $this->marcarEnProceso($tenant->id, $detalles);

        if ($enviarAuto) {
            SendVoidedToSunat::dispatch($voided->id);
            $voided->update(['sunat_status' => 'enviado']);
        }

        return ['ok' => true, 'voided' => $voided];
    }

    /**
     * Valida cada detalle contra las reglas de SUNAT antes de enviar.
     *
     * @param  array<int,array<string,mixed>>  $detalles
     * @return array<int,string> Mensajes de error (vacío = todo ok).
     */
    public function validarDetalles(int $tenantId, array $detalles): array
    {
        $errors = [];
        $hoy = Carbon::today('America/Lima');
        $limite = $hoy->copy()->subDays(7)->startOfDay();

        foreach ($detalles as $detalle) {
            $tipo = $detalle['tipo_documento'] ?? null;
            $serie = $detalle['serie'] ?? null;
            $correlativo = $detalle['correlativo'] ?? null;

            if (! $tipo || ! $serie || ! $correlativo) {
                $errors[] = 'Cada detalle requiere tipo_documento, serie y correlativo.';

                continue;
            }

            $model = self::MODELOS[$tipo] ?? null;
            if (! $model) {
                $errors[] = "Tipo de documento {$tipo} no soportado para anulación.";

                continue;
            }

            $document = $model::where('tenant_id', $tenantId)
                ->where('serie', $serie)
                ->where('correlativo', $correlativo)
                ->first();

            $ref = "{$serie}-{$correlativo}";

            if (! $document) {
                $errors[] = "Documento {$ref} no existe.";

                continue;
            }

            $status = strtolower((string) $document->sunat_status);
            if ($status !== 'aceptado') {
                $errors[] = "Documento {$ref} no está aceptado por SUNAT (estado actual: {$status}).";

                continue;
            }

            $fechaEmision = $document->fecha_emision instanceof Carbon
                ? $document->fecha_emision
                : Carbon::parse((string) $document->fecha_emision);

            if ($fechaEmision->lt($limite)) {
                $errors[] = "Documento {$ref} ya pasó el plazo de 7 días para anulación (emitido el {$fechaEmision->format('Y-m-d')}).";

                continue;
            }

            if ($tipo === '01') {
                $hasNC = CreditNote::where('tenant_id', $tenantId)
                    ->where('doc_afectado_tipo', '01')
                    ->where('doc_afectado_serie', $serie)
                    ->where('doc_afectado_correlativo', $correlativo)
                    ->exists();

                if ($hasNC) {
                    $errors[] = "La factura {$ref} tiene una nota de crédito asociada. Usa la NC en vez de anular la factura.";

                    continue;
                }
            }

            $duplicate = VoidedDocument::where('tenant_id', $tenantId)
                ->whereIn('sunat_status', ['pendiente', 'enviado', 'aceptado'])
                ->whereJsonContains('detalles', [
                    'tipo_documento' => $tipo,
                    'serie' => $serie,
                    'correlativo' => $correlativo,
                ])
                ->first();

            if ($duplicate) {
                $errors[] = "Ya existe una comunicación de baja para {$ref} ({$duplicate->identifier}).";

                continue;
            }
        }

        return $errors;
    }

    /** @param  array<int,array<string,mixed>>  $detalles */
    public function marcarEnProceso(int $tenantId, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            $model = self::MODELOS[$detalle['tipo_documento']] ?? null;
            if (! $model) {
                continue;
            }

            $model::where('tenant_id', $tenantId)
                ->where('serie', $detalle['serie'])
                ->where('correlativo', $detalle['correlativo'])
                ->update(['sunat_status' => 'anulacion_en_proceso']);
        }
    }

    /** Modelos de la Reversión (RR): retención (20) y percepción (40). */
    private const MODELOS_REVERSION = [
        '20' => Retention::class,
        '40' => Perception::class,
    ];

    /**
     * Crea una Reversión (RR) para dar de baja retenciones (20) o percepciones (40).
     *
     * @param  array<int,array<string,mixed>>  $detalles
     * @return array{ok:bool, errores?:array<int,string>, voided?:VoidedDocument, meta?:array<string,mixed>}
     */
    public function crearReversion(Tenant $tenant, string $fechaGeneracion, ?string $fechaComunicacion, array $detalles, bool $enviarAuto = true): array
    {
        $fechaCom = $fechaComunicacion ?: now()->format('Y-m-d');

        $errores = $this->validarReversion($tenant->id, $detalles);
        if (! empty($errores)) {
            return ['ok' => false, 'errores' => $errores];
        }

        $fechaId = str_replace('-', '', $fechaCom);
        $last = VoidedDocument::where('tenant_id', $tenant->id)
            ->where('identifier', 'like', "RR-{$fechaId}-%")
            ->max('correlativo') ?? 0;

        $correlativo = str_pad((string) ((int) $last + 1), 3, '0', STR_PAD_LEFT);
        $identifier = "RR-{$fechaId}-{$correlativo}";

        $voided = VoidedDocument::create([
            'tenant_id' => $tenant->id,
            'ambiente' => $tenant->environment === 'produccion' ? 'produccion' : 'prueba',
            'identifier' => $identifier,
            'correlativo' => $correlativo,
            'fecha_generacion' => $fechaGeneracion,
            'fecha_comunicacion' => $fechaCom,
            'total_documentos' => count($detalles),
            'detalles' => $detalles,
            'sunat_status' => 'pendiente',
        ]);

        foreach ($detalles as $detalle) {
            $model = self::MODELOS_REVERSION[$detalle['tipo_documento']] ?? null;
            if ($model) {
                $model::where('tenant_id', $tenant->id)
                    ->where('serie', $detalle['serie'])
                    ->where('correlativo', $detalle['correlativo'])
                    ->update(['sunat_status' => 'anulacion_en_proceso']);
            }
        }

        if ($enviarAuto) {
            SendReversionToSunat::dispatch($voided->id);
            $voided->update(['sunat_status' => 'enviado']);
        }

        return ['ok' => true, 'voided' => $voided, 'meta' => ['identifier' => $identifier]];
    }

    /**
     * @param  array<int,array<string,mixed>>  $detalles
     * @return array<int,string>
     */
    private function validarReversion(int $tenantId, array $detalles): array
    {
        $errores = [];

        foreach ($detalles as $detalle) {
            $tipo = $detalle['tipo_documento'] ?? null;
            $serie = $detalle['serie'] ?? null;
            $correlativo = $detalle['correlativo'] ?? null;
            $model = self::MODELOS_REVERSION[$tipo] ?? null;

            if (! $model) {
                $errores[] = "Tipo de documento {$tipo} no soportado para reversión.";

                continue;
            }

            $document = $model::where('tenant_id', $tenantId)
                ->where('serie', $serie)
                ->where('correlativo', $correlativo)
                ->first();

            $ref = "{$serie}-{$correlativo}";

            if (! $document) {
                $errores[] = "Documento {$ref} no existe.";

                continue;
            }

            if (strtolower((string) $document->sunat_status) !== 'aceptado') {
                $errores[] = "Documento {$ref} no está aceptado por SUNAT (estado: {$document->sunat_status}).";

                continue;
            }

            $duplicate = VoidedDocument::where('tenant_id', $tenantId)
                ->where('identifier', 'like', 'RR-%')
                ->whereIn('sunat_status', ['pendiente', 'enviado', 'aceptado'])
                ->whereJsonContains('detalles', [
                    'tipo_documento' => $tipo,
                    'serie' => $serie,
                    'correlativo' => $correlativo,
                ])
                ->first();

            if ($duplicate) {
                $errores[] = "Ya existe una reversión para {$ref} ({$duplicate->identifier}).";
            }
        }

        return $errores;
    }
}
