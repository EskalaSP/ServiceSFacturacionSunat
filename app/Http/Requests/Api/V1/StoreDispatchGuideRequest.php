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

            'destinatario.tipo_doc' => 'required|string|in:1,6',
            'destinatario.num_doc' => 'required|string|max:15',
            'destinatario.razon_social' => 'required|string|max:255',

            'cod_traslado' => 'required|string|max:2',
            'mod_traslado' => 'required|string|in:01,02',
            'fecha_traslado' => 'required|date',
            'peso_total' => 'required|numeric|gt:0',
            'und_peso_total' => 'nullable|string|max:3',
            'num_bultos' => 'nullable|integer|min:1',

            'llegada_ubigeo' => 'required|string|size:6',
            'llegada_direccion' => 'required|string|max:500',
            'partida_ubigeo' => 'required|string|size:6',
            'partida_direccion' => 'required|string|max:500',

            'transportista' => 'nullable|array',
            'transportista.tipo_doc' => 'required_with:transportista|string',
            'transportista.num_doc' => 'required_with:transportista|string',
            'transportista.razon_social' => 'required_with:transportista|string',

            'vehiculo' => 'nullable|array',
            'vehiculo.placa' => 'required_with:vehiculo|string',

            'conductor' => 'nullable|array',
            'conductor.tipo_doc' => 'required_with:conductor|string',
            'conductor.num_doc' => 'required_with:conductor|string',

            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.unidad' => 'nullable|string|max:5',
            'items.*.codigo' => 'nullable|string|max:50',
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
