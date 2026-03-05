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

    public function rules(): array
    {
        return [
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
            'transportista.num_doc' => 'required_with:transportista|string',
            'transportista.razon_social' => 'required_with:transportista|string',
            'transportista.nro_mtc' => 'nullable|string|max:20',

            // Vehículo (transporte privado)
            'vehiculo' => 'nullable|array',
            'vehiculo.placa' => 'required_with:vehiculo|string',
            'vehiculo.secundarios' => 'nullable|array',
            'vehiculo.secundarios.*.placa' => 'required|string',

            // Conductor único
            'conductor' => 'nullable|array',
            'conductor.tipo_doc' => 'required_with:conductor|string',
            'conductor.num_doc' => 'required_with:conductor|string',
            'conductor.tipo' => 'nullable|string|in:Principal,Secundario',
            'conductor.nombres' => 'nullable|string|max:100',
            'conductor.apellidos' => 'nullable|string|max:100',
            'conductor.licencia' => 'nullable|string|max:20',

            // Múltiples conductores
            'conductores' => 'nullable|array',
            'conductores.*.tipo_doc' => 'required|string',
            'conductores.*.num_doc' => 'required|string',
            'conductores.*.tipo' => 'nullable|string|in:Principal,Secundario',
            'conductores.*.nombres' => 'nullable|string|max:100',
            'conductores.*.apellidos' => 'nullable|string|max:100',
            'conductores.*.licencia' => 'nullable|string|max:20',

            // Items
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.unidad' => 'nullable|string|max:5',
            'items.*.codigo' => 'nullable|string|max:50',
            'items.*.cod_prod_sunat' => 'nullable|string|max:20',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $validator->errors(),
        ], 422));
    }
}
