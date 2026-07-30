<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Boleta;
use App\Models\Invoice;
use App\Services\DocumentCalculationService;
use Illuminate\Contracts\Validation\Validator;

/**
 * ERR-3286 / ERR-3503: el Importe Total de la nota de crédito no puede superar el
 * del documento que modifica (factura '01' o boleta '03') emitido por esta API.
 *
 * Reglas (hoja NotaCredito2_0, filas 111-122):
 *  - Aplica cuando el motivo (Cat. 09) es distinto de '10'.
 *  - Factura (01): NC total ≤ documento total (estricto).
 *  - Boleta (03): NC total ≤ documento total + 1 (tolerancia).
 *  - NO aplica si la moneda de la NC difiere de la del documento.
 *  - NO aplica si el documento no fue emitido por esta API (SUNAT valida al recibir).
 */
trait ValidatesNotaCreditoMonto
{
    protected function validarMontoVsDocumentoModificado(Validator $v): void
    {
        // Si ya hay errores (p. ej. ítems inválidos), no intentamos calcular.
        if ($v->errors()->isNotEmpty()) {
            return;
        }

        $tenant = $this->get('tenant');
        $tipo = $this->input('doc_afectado_tipo');

        if (! $tenant || $this->input('cod_motivo') === '10' || ! in_array($tipo, ['01', '03'], true)) {
            return;
        }

        $model = $tipo === '01' ? Invoice::class : Boleta::class;
        $doc = $model::query()
            ->where('tenant_id', $tenant->id)
            ->where('serie', $this->input('doc_afectado_serie'))
            ->where('correlativo', (int) $this->input('doc_afectado_correlativo'))
            ->first();

        // Documento no emitido por esta API: no se puede validar localmente.
        if (! $doc) {
            return;
        }

        // Distinta moneda: la validación SUNAT no aplica.
        if (($this->input('tipo_moneda', 'PEN')) !== $doc->tipo_moneda) {
            return;
        }

        try {
            $data = $this->all();
            $fecha = $data['fecha_emision'] ?? null;
            $calc = app(DocumentCalculationService::class);
            $items = $calc->calculateItems($data['items'] ?? [], $tenant, $fecha);
            $ncTotal = round((float) $calc->calculateTotals($items, $data, $tenant, $fecha)['mto_imp_venta'], 2);
        } catch (\Throwable $e) {
            return; // si no se puede calcular, se deja que SUNAT valide en recepción
        }

        $docTotal = round((float) $doc->mto_imp_venta, 2);
        $tolerancia = $tipo === '03' ? 1.0 : 0.0; // Boletas: +1 (regla SUNAT)

        if ($ncTotal > $docTotal + $tolerancia) {
            $serie = $this->input('doc_afectado_serie');
            $corr = $this->input('doc_afectado_correlativo');
            $v->errors()->add(
                'doc_afectado_correlativo',
                "El importe total de la nota de crédito ({$ncTotal}) no puede superar el del documento que modifica {$serie}-{$corr} ({$docTotal}) (ERR-3286/3503)."
            );
        }
    }
}
