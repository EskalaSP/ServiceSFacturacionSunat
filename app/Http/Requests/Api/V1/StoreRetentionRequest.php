<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesManualCorrelativo;
use App\Models\Retention;
use App\Rules\SunatCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRetentionRequest extends FormRequest
{
    use ValidatesManualCorrelativo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serie' => 'required|string|size:4|regex:/^R[A-Z0-9]{3}$/',
            // Correlativo opcional: si se omite, se autoincrementa según la serie.
            'correlativo' => 'nullable|integer|min:1',
            'cod_local' => 'nullable|string|size:4',
            'fecha_emision' => 'required|date',

            // Proveedor (a quien se retiene)
            'proveedor.tipo_doc' => ['required', 'string', new SunatCatalog('06')],
            'proveedor.num_doc' => 'required|string|max:15',
            'proveedor.razon_social' => 'required|string|max:1500',
            'proveedor.direccion' => 'nullable|string|max:500',

            // Régimen retención — Cat 23 (01=3%, 02=6%)
            'regimen' => ['required', 'string', new SunatCatalog('23')],
            'tasa' => 'required|numeric|min:0|max:100',

            'observacion' => 'nullable|string|max:500',

            // Documentos relacionados (items)
            'documentos' => 'required|array|min:1',
            'documentos.*.tipo_doc' => 'required|string|in:01,03,12',
            'documentos.*.num_doc' => 'required|string',
            'documentos.*.fecha_emision' => 'required|date',
            'documentos.*.imp_total' => 'required|numeric|gt:0',
            'documentos.*.moneda' => 'nullable|string|in:PEN,USD,EUR',

            // Pagos del documento
            'documentos.*.pagos' => 'required|array|min:1',
            'documentos.*.pagos.*.moneda' => 'nullable|string|in:PEN,USD,EUR',
            'documentos.*.pagos.*.importe' => 'required|numeric|gt:0',
            'documentos.*.pagos.*.fecha' => 'required|date',

            // Retención del documento
            'documentos.*.fecha_retencion' => 'required|date',
            'documentos.*.imp_retenido' => 'nullable|numeric|min:0',
            'documentos.*.imp_pagar' => 'nullable|numeric|min:0',

            // Tipo de cambio (si moneda != PEN)
            'documentos.*.tipo_cambio' => 'nullable|array',
            'documentos.*.tipo_cambio.moneda_ref' => 'required_with:documentos.*.tipo_cambio|string',
            'documentos.*.tipo_cambio.moneda_obj' => 'required_with:documentos.*.tipo_cambio|string',
            'documentos.*.tipo_cambio.factor' => 'required_with:documentos.*.tipo_cambio|numeric|gt:0',
            'documentos.*.tipo_cambio.fecha' => 'required_with:documentos.*.tipo_cambio|date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validarCorrelativoManual($v, Retention::class);
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
