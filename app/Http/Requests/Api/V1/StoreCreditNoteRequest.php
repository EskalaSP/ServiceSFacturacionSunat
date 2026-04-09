<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serie' => 'required|string|size:4',
            'cod_local' => 'nullable|string|size:4',
            'fecha_emision' => 'required|date',
            'tipo_moneda' => 'nullable|string|in:PEN,USD',

            'cliente.tipo_doc' => 'required|string|in:0,1,4,6,7,A',
            'cliente.num_doc' => 'required|string|max:15',
            'cliente.razon_social' => 'required|string|max:255',
            'cliente.direccion' => 'nullable|string|max:500',
            'cliente.email' => 'nullable|email',
            'cliente.telefono' => 'nullable|string|max:20',

            'doc_afectado_tipo' => 'required|string|in:01,03',
            'doc_afectado_serie' => 'required|string|size:4',
            'doc_afectado_correlativo' => 'required|string',
            'cod_motivo' => 'required|string|max:2',
            'des_motivo' => 'required|string|max:255',

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
            'items.*.unidad' => 'required|string|in:4A,BJ,BLL,BG,BO,BX,CT,CMK,CMQ,CMT,CEN,CY,CJ,DZN,DZP,BE,GLI,GRM,GRO,HLT,LEF,SET,KGM,KTM,KWH,KT,CA,LBR,LTR,MWH,MTR,MTK,MTQ,MGM,MLT,MMT,MMK,MMQ,MLL,UM,ONZ,PF,PK,PR,FOT,FTK,FTQ,C62,PG,ST,INH,RM,DR,STN,LTN,TNE,TU,NIU,ZZ,GLL,YRD,YDK,U2,HUR,QD,HD,JG,JR,CH,AV,SA,BT,HT,RD,RL,SEC,DAY,MON',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.porcentaje_igv' => 'nullable|numeric',
            'items.*.tip_afe_igv' => 'nullable|string|in:10,11,12,13,14,15,16,17,20,21,30,31,32,33,34,35,36,40',
            'items.*.igv' => 'nullable|numeric',
            'items.*.isc' => 'nullable|numeric',
            'items.*.icbper' => 'nullable|numeric',
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

            'guias' => 'nullable|array',
            'guias.*.tipo_doc' => 'required_with:guias|string',
            'guias.*.nro_doc' => 'required_with:guias|string',
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
