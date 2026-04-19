<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreVoidedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_generacion' => 'required|date',
            'fecha_comunicacion' => 'sometimes|date',
            'detalles' => 'required|array|min:1',
            // Tipos soportados: 01=Factura, 03=Boleta, 07=NC, 08=ND, 20=Retención, 40=Percepción
            'detalles.*.tipo_documento' => 'required|string|in:01,03,07,08,20,40',
            'detalles.*.serie' => 'required|string|size:4',
            'detalles.*.correlativo' => 'required|string',
            'detalles.*.motivo' => 'required|string|max:255',
        ];
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
