<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Reglas del Contrato de Colaboración Empresarial sin contabilidad independiente
 * (RS 048-2026, vig. 01/01/2027). Bloque opcional; se ancla en el "número de
 * contrato". Va en el XML como cac:ContractDocumentReference (inyectado).
 *
 *  - ERR-3497: tipo debe ser '1' (ventas) o '2' (adquisiciones).
 *  - ERR-3501: número alfanumérico de 1 a 50 caracteres.
 *  - ERR-3502: descripción alfanumérica de 1 a 250 caracteres.
 *  - ERR-3500: porcentaje decimal positivo, hasta 2 enteros y 2 decimales (0–99.99).
 *  - ERR-3499: si se informa el número, tipo/descripción/porcentaje son obligatorios.
 *  - ERR-3498: un solo número de contrato (garantizado por ser objeto único).
 */
trait ValidatesContratoColaboracion
{
    protected function reglasContratoColaboracion(): array
    {
        return [
            'contrato_colaboracion' => 'nullable|array',
            'contrato_colaboracion.numero' => 'nullable|string|max:50',
            'contrato_colaboracion.tipo' => 'required_with:contrato_colaboracion.numero|in:1,2',
            'contrato_colaboracion.descripcion' => 'required_with:contrato_colaboracion.numero|string|max:250',
            'contrato_colaboracion.porcentaje' => 'required_with:contrato_colaboracion.numero|numeric|min:0|max:99.99',
        ];
    }
}
