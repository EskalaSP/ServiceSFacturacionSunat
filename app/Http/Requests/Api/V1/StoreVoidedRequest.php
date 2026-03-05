<?php

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
            'detalles.*.tipo_documento' => 'required|string|in:01,03,07,08',
            'detalles.*.serie' => 'required|string|size:4',
            'detalles.*.correlativo' => 'required|string',
            'detalles.*.motivo' => 'required|string|max:255',
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
