<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBoletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serie' => 'required|string|size:4|regex:/^B/',
            'cod_local' => 'nullable|string|size:4',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'tipo_operacion' => 'nullable|string|max:4',
            'tipo_moneda' => 'nullable|string|in:PEN,USD',
            'forma_pago' => 'nullable|string|in:Contado,Credito',

            'cliente.tipo_doc' => 'required|string|in:0,1,4,6,7,A',
            'cliente.num_doc' => ['required', 'string', 'max:15'],
            'cliente.razon_social' => 'required|string|max:255',
            'cliente.direccion' => 'nullable|string|max:500',
            'cliente.email' => 'nullable|email',

            'mto_oper_gravadas' => 'nullable|numeric|min:0',
            'mto_oper_exoneradas' => 'nullable|numeric|min:0',
            'mto_oper_inafectas' => 'nullable|numeric|min:0',
            'mto_oper_gratuitas' => 'nullable|numeric|min:0',
            'mto_igv' => 'nullable|numeric|min:0',
            'total_impuestos' => 'nullable|numeric|min:0',
            'valor_venta' => 'nullable|numeric|min:0',
            'sub_total' => 'nullable|numeric|min:0',
            'mto_imp_venta' => 'nullable|numeric|min:0',
            'sum_otros_descuentos' => 'nullable|numeric|min:0',

            'items' => 'required|array|min:1',
            'items.*.codigo' => 'nullable|string|max:50',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.unidad' => 'required|string|in:NIU,ZZ,KGM,TNE,LTR,GLL,MTR,KWH,HUR,DAY,MON,BX,PK,DZN,SET,MTK,MTQ,GRM,MGM,MLT,CMT,ONZ,FOT,INH,LBR,OZA,BLL,BAG,CEN,SA',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.porcentaje_igv' => 'nullable|numeric',
            'items.*.tip_afe_igv' => 'nullable|string|in:10,11,12,13,14,15,16,17,20,21,30,31,32,33,34,35,36,40',
            'items.*.igv' => 'nullable|numeric',
            'items.*.isc' => 'nullable|numeric',
            'items.*.icbper' => 'nullable|numeric',
            'items.*.factor_icbper' => 'nullable|numeric',
            'items.*.mto_valor_unitario' => 'nullable|numeric|min:0',
            'items.*.mto_valor_venta' => 'nullable|numeric',
            'items.*.mto_base_igv' => 'nullable|numeric|min:0',
            'items.*.total_impuestos' => 'nullable|numeric',

            'items.*.descuentos' => 'nullable|array',
            'items.*.descuentos.*.cod_tipo' => 'required_with:items.*.descuentos|string',
            'items.*.descuentos.*.monto_base' => 'required_with:items.*.descuentos|numeric|min:0',
            'items.*.descuentos.*.factor' => 'required_with:items.*.descuentos|numeric',
            'items.*.descuentos.*.monto' => 'required_with:items.*.descuentos|numeric|min:0',

            'leyenda' => 'nullable|string|max:500',

            'cuotas' => 'nullable|array',
            'cuotas.*.monto' => 'required_with:cuotas|numeric|gt:0',
            'cuotas.*.fecha_pago' => 'required_with:cuotas|date',

            'guias' => 'nullable|array',
            'guias.*.tipo_doc' => 'required_with:guias|string',
            'guias.*.nro_doc' => 'required_with:guias|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $items = $this->input('items', []);
            $total = 0;

            foreach ($items as $item) {
                $qty = (float) ($item['cantidad'] ?? 0);
                $price = (float) ($item['precio_unitario'] ?? 0);
                $total += $qty * $price;
            }

            $tipoDoc = $this->input('cliente.tipo_doc', '0');

            if ($total > 700 && $tipoDoc === '0') {
                $v->errors()->add(
                    'cliente.tipo_doc',
                    'Para boletas mayores a S/ 700.00 es obligatorio consignar el documento de identidad del cliente (DNI, RUC, CE, etc.).'
                );
            }
        });
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
