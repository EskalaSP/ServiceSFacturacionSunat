<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;

/**
 * Validación reutilizable del correlativo manual opcional.
 *
 * El correlativo es autoincremental por defecto (según la configuración de la
 * serie). Si el cliente envía un `correlativo` manual, se valida que no exista
 * ya un comprobante de esa misma serie con ese número para el mismo tenant,
 * evitando duplicados que SUNAT rechazaría.
 */
trait ValidatesManualCorrelativo
{
    /**
     * @param  class-string<Model>  $documentClass  Modelo del comprobante (Invoice, Boleta, ...)
     */
    protected function validarCorrelativoManual(Validator $v, string $documentClass): void
    {
        $correlativo = $this->input('correlativo');
        $tenant = $this->get('tenant');
        $serie = $this->input('serie');

        // Sin correlativo manual → autoincremental, no hay nada que validar.
        if ($correlativo === null || $correlativo === '' || ! $tenant || ! $serie) {
            return;
        }

        $existe = $documentClass::query()
            ->where('tenant_id', $tenant->id)
            ->where('serie', $serie)
            ->where('correlativo', (int) $correlativo)
            ->exists();

        if ($existe) {
            $v->errors()->add(
                'correlativo',
                "El correlativo {$correlativo} ya existe para la serie {$serie}. Use otro número o deje el campo vacío para autoincremento."
            );
        }
    }
}
