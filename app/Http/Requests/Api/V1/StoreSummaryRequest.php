<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_resumen' => 'required|date',

            // Modo anulación
            'anular' => 'nullable|array',
            'anular.*.id' => 'required_with:anular|integer',
            'anular.*.motivo' => 'required_with:anular|string|max:255',
            'anular.*.tipo_documento' => 'nullable|string|in:03,07,08',
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
