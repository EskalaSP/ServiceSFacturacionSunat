<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDispatchGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizar payload antes de validar:
     *  1. Si la ruta es /guias-remision-transportista → forzar tipo_documento=31
     *  2. Si doc_relacionado viene como objeto único → envolver en array
     */
    protected function prepareForValidation(): void
    {
        // Ruta GRT → forzar tipo_documento='31'
        if (str_contains($this->path(), 'guias-remision-transportista')) {
            $this->merge(['tipo_documento' => '31']);
        }

        // doc_relacionado como objeto único → array
        $doc = $this->input('doc_relacionado');
        if (is_array($doc) && isset($doc['tipo_codigo'])) {
            $this->merge(['doc_relacionado' => [$doc]]);
        }
    }

    public function rules(): array
    {
        return [
            // tipo_documento: '09' Guía Remitente (default) o '31' Guía Transportista
            'tipo_documento' => 'sometimes|string|in:09,31',
            'serie' => 'required|string|size:4',
            'fecha_emision' => 'required|date',
            'observacion' => 'nullable|string|max:500',

            // Destinatario
            'destinatario.tipo_doc' => 'required|string|in:1,6',
            'destinatario.num_doc' => 'required|string|max:15',
            'destinatario.razon_social' => 'required|string|max:255',

            // Tercero (proveedor)
            'tercero' => 'nullable|array',
            'tercero.tipo_doc' => 'required_with:tercero|string|in:1,6',
            'tercero.num_doc' => 'required_with:tercero|string|max:15',
            'tercero.razon_social' => 'required_with:tercero|string|max:255',

            // Comprador
            'comprador' => 'nullable|array',
            'comprador.tipo_doc' => 'required_with:comprador|string|in:1,6',
            'comprador.num_doc' => 'required_with:comprador|string|max:15',
            'comprador.razon_social' => 'required_with:comprador|string|max:255',

            // Envío
            'cod_traslado' => 'required|string|max:2',
            'mod_traslado' => 'required|string|in:01,02',
            'fecha_traslado' => 'required|date',
            'peso_total' => 'required|numeric|gt:0',
            'und_peso_total' => 'nullable|string|max:3',
            'num_bultos' => 'nullable|integer|min:1',

            // Indicadores (M1L, transbordo, retorno vacío, etc.)
            'indicadores' => 'nullable|array',
            'indicadores.*' => 'string',

            // Direcciones
            'llegada_ubigeo' => 'required|string|size:6',
            'llegada_direccion' => 'required|string|max:500',
            'llegada_ruc' => 'nullable|string|size:11',
            'llegada_cod_local' => 'nullable|string|max:5',
            'partida_ubigeo' => 'required|string|size:6',
            'partida_direccion' => 'required|string|max:500',
            'partida_ruc' => 'nullable|string|size:11',
            'partida_cod_local' => 'nullable|string|max:5',

            // Transportista (transporte público)
            'transportista' => 'nullable|array',
            'transportista.tipo_doc' => 'required_with:transportista|string',
            'transportista.num_doc' => 'required_with:transportista|string|max:11',
            'transportista.razon_social' => 'required_with:transportista|string|max:255',
            'transportista.nro_mtc' => 'nullable|string|max:20',

            // Vehículo (transporte privado)
            'vehiculo' => 'nullable|array',
            'vehiculo.placa' => 'required_with:vehiculo|string|max:10',
            'vehiculo.secundarios' => 'nullable|array',
            'vehiculo.secundarios.*.placa' => 'required|string|max:10',

            // Conductor único
            'conductor' => 'nullable|array',
            'conductor.tipo_doc' => 'required_with:conductor|string',
            'conductor.num_doc' => 'required_with:conductor|string|max:15',
            'conductor.tipo' => 'nullable|string|in:Principal,Secundario',
            'conductor.nombres' => 'required_with:conductor|string|max:100',
            'conductor.apellidos' => 'required_with:conductor|string|max:100',
            'conductor.licencia' => 'required_with:conductor|string|max:20',

            // Múltiples conductores
            'conductores' => 'nullable|array',
            'conductores.*.tipo_doc' => 'required|string',
            'conductores.*.num_doc' => 'required|string|max:15',
            'conductores.*.tipo' => 'nullable|string|in:Principal,Secundario',
            'conductores.*.nombres' => 'required|string|max:100',
            'conductores.*.apellidos' => 'required|string|max:100',
            'conductores.*.licencia' => 'required|string|max:20',

            // Documento(s) relacionado(s) — OBLIGATORIO para GRT, opcional para GRR.
            // Siempre array; si viene un solo objeto, envuélvalo en [] antes de enviar.
            'doc_relacionado' => 'nullable|array|max:5',
            'doc_relacionado.*.tipo_codigo' => 'required_with:doc_relacionado|string|in:01,03,04,09,12,48,50,52,65,66,67,68,69,71,72,73,74,75,76,77,78,80,82,91',
            'doc_relacionado.*.numero' => 'required_with:doc_relacionado|string|max:100',
            'doc_relacionado.*.tipo_descripcion' => 'nullable|string|max:120',
            'doc_relacionado.*.ruc_emisor' => 'nullable|string|size:11',

            // Remitente — solo GRT (el tenant es el transportista, no el remitente)
            'remitente' => 'nullable|array',
            'remitente.tipo_doc' => 'required_with:remitente|string|in:1,6',
            'remitente.num_doc' => 'required_with:remitente|string|max:15',
            'remitente.razon_social' => 'required_with:remitente|string|max:250',

            // Subcontratación (solo GRT)
            'datos_subcontratador' => 'nullable|array',
            'datos_subcontratador.num_doc' => 'required_with:datos_subcontratador|string|size:11',
            'datos_subcontratador.razon_social' => 'required_with:datos_subcontratador|string|max:250',

            // Pagador del flete (solo GRT, cuando paga un tercero distinto al remitente/subcontratador)
            'datos_pagador_flete' => 'nullable|array',
            'datos_pagador_flete.tipo' => 'required_with:datos_pagador_flete|string|in:remitente,subcontratador,tercero',
            'datos_pagador_flete.tipo_doc' => 'nullable|string|in:1,6',
            'datos_pagador_flete.num_doc' => 'nullable|string|max:15',
            'datos_pagador_flete.razon_social' => 'nullable|string|max:250',

            // Autorización especial (Cat D-37: MATPEL, MTC, etc.)
            'autorizacion_especial' => 'nullable|array',
            'autorizacion_especial.cod_emisor' => 'nullable|string|size:2',
            'autorizacion_especial.nro_autorizacion' => 'nullable|string|max:50',

            // Vehículo extendido con TUC y autorización especial
            'vehiculo.nro_circulacion' => 'nullable|string|max:15',
            'vehiculo.tuc' => 'nullable|string|max:15',
            'vehiculo.cod_emisor' => 'nullable|string|size:2',
            'vehiculo.nro_autorizacion' => 'nullable|string|max:50',
            'vehiculo.secundarios.*.nro_circulacion' => 'nullable|string|max:15',
            'vehiculo.secundarios.*.cod_emisor' => 'nullable|string|size:2',
            'vehiculo.secundarios.*.nro_autorizacion' => 'nullable|string|max:50',

            // Items
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.unidad' => 'nullable|string|max:5',
            'items.*.codigo' => 'nullable|string|max:50',
            'items.*.cod_prod_sunat' => 'nullable|string|max:20',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tipoDocumento = $this->input('tipo_documento', '09');
            $indicadores = $this->input('indicadores', []);
            $esM1L = is_array($indicadores) && collect($indicadores)->contains(fn ($i) => str_contains($i, 'M1L'));
            $modTraslado = $this->input('mod_traslado');
            $esGRT = $tipoDocumento === '31';

            // ─── Reglas específicas GRT ──────────────────────────────────
            if ($esGRT) {
                // GRT requiere datos del remitente (el transportista es el emisor, no el remitente)
                if (empty($this->input('remitente'))) {
                    $validator->errors()->add(
                        'remitente',
                        'La Guía de Remisión Transportista requiere los datos del remitente (quien envía la carga).'
                    );
                }

                // El remitente no puede ser el mismo transportista (tenant)
                $remitente = $this->input('remitente', []);
                $tenant = $this->get('tenant');
                if ($tenant && ($remitente['num_doc'] ?? null) === $tenant->ruc) {
                    $validator->errors()->add(
                        'remitente.num_doc',
                        'El remitente no puede ser el mismo que el transportista emisor (RUC del tenant).'
                    );
                }

                // GRT requiere documento relacionado OBLIGATORIO (factura, boleta, DAM, GRR, etc.)
                if (empty($this->input('doc_relacionado'))) {
                    $validator->errors()->add(
                        'doc_relacionado',
                        'La Guía de Remisión Transportista requiere al menos un documento relacionado (factura, boleta, DAM, GRR, etc.).'
                    );
                }

                // GRT exige vehículo + conductor (el transportista es el que emite)
                if (empty($this->input('vehiculo'))) {
                    $validator->errors()->add('vehiculo', 'La Guía Transportista requiere datos del vehículo.');
                }
                if (empty($this->input('conductor')) && empty($this->input('conductores'))) {
                    $validator->errors()->add('conductor', 'La Guía Transportista requiere al menos un conductor.');
                }

                // Si hay subcontratación, debe informar pagador de flete
                if (! empty($this->input('datos_subcontratador')) && empty($this->input('datos_pagador_flete'))) {
                    $validator->errors()->add(
                        'datos_pagador_flete',
                        'Si existe transporte subcontratado, debe informar quién paga el flete (remitente/subcontratador/tercero).'
                    );
                }

                // Si el pagador es tercero, deben venir sus datos de identidad
                if ($this->input('datos_pagador_flete.tipo') === 'tercero') {
                    if (empty($this->input('datos_pagador_flete.num_doc'))) {
                        $validator->errors()->add('datos_pagador_flete.num_doc', 'Si el pagador es tercero, debe informar su número de documento.');
                    }
                    if (empty($this->input('datos_pagador_flete.razon_social'))) {
                        $validator->errors()->add('datos_pagador_flete.razon_social', 'Si el pagador es tercero, debe informar su razón social.');
                    }
                }
            }

            // ─── Reglas existentes GRR ───────────────────────────────────
            if (! $esGRT) {
                // GRR transporte público (01) requiere transportista, excepto M1L
                if ($modTraslado === '01' && ! $esM1L) {
                    if (empty($this->input('transportista'))) {
                        $validator->errors()->add('transportista', 'El transportista es requerido para transporte público.');
                    }
                }

                // GRR transporte privado (02) requiere vehículo + conductor, excepto M1L
                if ($modTraslado === '02' && ! $esM1L) {
                    if (empty($this->input('vehiculo'))) {
                        $validator->errors()->add('vehiculo', 'El vehículo es requerido para transporte privado.');
                    }
                    if (empty($this->input('conductor')) && empty($this->input('conductores'))) {
                        $validator->errors()->add('conductor', 'Al menos un conductor es requerido para transporte privado.');
                    }
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'estado' => 'error',
            'mensaje' => 'Error de validación',
            'errores' => $validator->errors(),
        ], 422));
    }
}
