<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'tipo_documento' => 'required|string|in:01,03,07,08',
            'serie' => 'required|string|size:4',
            'cod_local' => 'nullable|string|size:4',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'tipo_operacion' => 'nullable|string|max:4',
            'tipo_moneda' => 'nullable|string|in:PEN,USD',
            'forma_pago' => 'nullable|string|in:Contado,Credito',

            // Cliente
            'cliente.tipo_doc' => 'required|string|in:0,1,4,6,7,A',
            'cliente.num_doc' => 'required|string|max:15',
            'cliente.razon_social' => 'required|string|max:255',
            'cliente.direccion' => 'nullable|string|max:500',
            'cliente.email' => 'nullable|email',

            // Montos
            'mto_oper_gravadas' => 'nullable|numeric|min:0',
            'mto_oper_exoneradas' => 'nullable|numeric|min:0',
            'mto_oper_inafectas' => 'nullable|numeric|min:0',
            'mto_oper_gratuitas' => 'nullable|numeric|min:0',
            'mto_igv' => 'nullable|numeric|min:0',
            'total_impuestos' => 'nullable|numeric|min:0',
            'valor_venta' => 'nullable|numeric|min:0',
            'sub_total' => 'nullable|numeric|min:0',
            'mto_imp_venta' => 'nullable|numeric|min:0',

            // Items
            'items' => 'required|array|min:1',
            'items.*.codigo' => 'nullable|string|max:50',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.unidad' => 'nullable|string|max:5',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.porcentaje_igv' => 'nullable|numeric',
            'items.*.tip_afe_igv' => 'nullable|string|in:10,20,30,13,21,31,32,33,34,35,36',
            'items.*.igv' => 'nullable|numeric',
            'items.*.isc' => 'nullable|numeric',
            'items.*.icbper' => 'nullable|numeric',

            // Leyenda
            'leyenda' => 'nullable|string|max:500',

            // Crédito
            'cuotas' => 'nullable|array',
            'cuotas.*.monto' => 'required_with:cuotas|numeric|gt:0',
            'cuotas.*.fecha_pago' => 'required_with:cuotas|date',

            // Guías
            'guias' => 'nullable|array',
            'guias.*.tipo_doc' => 'required_with:guias|string',
            'guias.*.nro_doc' => 'required_with:guias|string',

            // Extras
            'detraccion' => 'nullable|array',
            'percepcion' => 'nullable|array',
            'anticipos' => 'nullable|array',
            'descuentos_globales' => 'nullable|array',
        ];

        // Validaciones adicionales para NC/ND
        if (in_array($this->input('tipo_documento'), ['07', '08'])) {
            $rules['doc_afectado_tipo'] = 'required|string|in:01,03';
            $rules['doc_afectado_serie'] = 'required|string|size:4';
            $rules['doc_afectado_correlativo'] = 'required|string';
            $rules['cod_motivo'] = 'required|string|max:2';
            $rules['des_motivo'] = 'required|string|max:255';
        }

        return $rules;
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
