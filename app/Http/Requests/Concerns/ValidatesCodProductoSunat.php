<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Validaciones de obligatoriedad del Código de Producto SUNAT (Catálogo N.° 25).
 *
 * El formato/existencia del código lo valida App\Rules\SunatProductCode a nivel
 * de campo. Aquí se resuelve la OBLIGATORIEDAD, que es condicional:
 *
 *  - Padrón (OBS-4331): si el emisor está en el padrón ind_padron = '12'
 *    (tenant->obligado_codigo_producto), cada ítem debe llevar el código.
 *  - Tipo de operación 0112 (ERR-3181): "Venta interna - Sustenta gastos
 *    deducibles persona natural" exige al menos un ítem con código '84121901'
 *    (créditos hipotecarios) o '80131501' (arrendamiento).
 */
trait ValidatesCodProductoSunat
{
    protected function validarCodProductoObligatorio(Validator $v): void
    {
        $tenant = $this->get('tenant');

        if (! $tenant || ! ($tenant->obligado_codigo_producto ?? false)) {
            return;
        }

        foreach ((array) $this->input('items', []) as $i => $item) {
            if (empty($item['cod_producto_sunat'])) {
                $v->errors()->add(
                    "items.{$i}.cod_producto_sunat",
                    'Su RUC está obligado por SUNAT (padrón "Obligado a enviar código de producto") a consignar el Código de producto SUNAT en cada ítem.'
                );
            }
        }
    }

    protected function validarTipoOperacion0112(Validator $v): void
    {
        if ($this->input('tipo_operacion') !== '0112') {
            return;
        }

        $codigos = array_column((array) $this->input('items', []), 'cod_producto_sunat');

        if (! array_intersect(['84121901', '80131501'], $codigos)) {
            $v->errors()->add(
                'items',
                "El tipo de operación 0112 (gastos deducibles persona natural) requiere al menos un ítem con Código de producto SUNAT '84121901' (crédito hipotecario) o '80131501' (arrendamiento de inmuebles)."
            );
        }
    }
}
