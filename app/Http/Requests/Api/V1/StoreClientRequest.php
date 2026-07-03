<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Acepta como alias los nombres cortos usados en el resto de la API
     * (tipo_doc/num_doc) y los normaliza a los canónicos del modelo
     * Client (tipo_documento/numero_documento).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if (! $this->filled('tipo_documento') && $this->filled('tipo_doc')) {
            $merge['tipo_documento'] = $this->input('tipo_doc');
        }
        if (! $this->filled('numero_documento') && $this->filled('num_doc')) {
            $merge['numero_documento'] = $this->input('num_doc');
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);

        return [
            'tipo_documento' => [$isUpdate ? 'sometimes' : 'required', 'string', 'in:0,1,4,6,7,A'],
            'numero_documento' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:15'],
            'razon_social' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:20',
            'ubigeo' => 'nullable|string|size:6',
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
