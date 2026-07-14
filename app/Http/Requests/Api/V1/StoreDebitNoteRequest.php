<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesManualCorrelativo;
use App\Models\DebitNote;
use App\Rules\SunatCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDebitNoteRequest extends FormRequest
{
    use ValidatesManualCorrelativo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serie' => 'required|string|size:4',
            // Correlativo opcional: si se omite, se autoincrementa según la serie.
            'correlativo' => 'nullable|integer|min:1',
            'cod_local' => 'nullable|string|size:4',
            'fecha_emision' => 'required|date',
            'tipo_moneda' => 'nullable|string|in:PEN,USD,EUR',

            // Cliente — Cat 06, longitudes Formato 1.3.4
            'cliente.tipo_doc' => ['required', 'string', new SunatCatalog('06')],
            'cliente.num_doc' => 'required|string|max:15',
            'cliente.razon_social' => 'required|string|max:1500',
            'cliente.direccion' => 'nullable|string|max:500',
            'cliente.email' => 'nullable|email',
            'cliente.telefono' => 'nullable|string|max:20',

            // Documento afectado
            'doc_afectado_tipo' => 'required|string|in:01,03,12',
            'doc_afectado_serie' => 'required|string|size:4',
            'doc_afectado_correlativo' => 'required|string',
            'cod_motivo' => ['required', 'string', new SunatCatalog('10')],
            'des_motivo' => 'required|string|max:250',

            // Montos calculados
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

            // Items
            'items' => 'required|array|min:1',
            'items.*.codigo' => 'nullable|string|max:30',
            'items.*.cod_producto_sunat' => 'nullable|string|max:8',
            'items.*.descripcion' => 'required|string|max:500',
            'items.*.unidad' => 'required|string|in:4A,BJ,BLL,BG,BO,BX,CT,CMK,CMQ,CMT,CEN,CY,CJ,DZN,DZP,BE,GLI,GRM,GRO,HLT,LEF,SET,KGM,KTM,KWH,KT,CA,LBR,LTR,MWH,MTR,MTK,MTQ,MGM,MLT,MMT,MMK,MMQ,MLL,UM,ONZ,PF,PK,PR,FOT,FTK,FTQ,C62,PG,ST,INH,RM,DR,STN,LTN,TNE,TU,NIU,ZZ,GLL,YRD,YDK,U2,HUR,QD,HD,JG,JR,CH,AV,SA,BT,HT,RD,RL,SEC,DAY,MON',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.porcentaje_igv' => 'nullable|numeric',
            'items.*.tip_afe_igv' => ['nullable', 'string', new SunatCatalog('07')],
            'items.*.igv' => 'nullable|numeric',
            'items.*.isc' => 'nullable|numeric',
            'items.*.tip_sis_isc' => ['nullable', 'string', new SunatCatalog('08')],
            'items.*.icbper' => 'nullable|numeric',
            'items.*.factor_icbper' => 'nullable|numeric',
            'items.*.mto_valor_unitario' => 'nullable|numeric|min:0',
            'items.*.mto_valor_venta' => 'nullable|numeric',
            'items.*.mto_base_igv' => 'nullable|numeric|min:0',
            'items.*.total_impuestos' => 'nullable|numeric',

            // Descuentos por ítem — Cat 53
            'items.*.descuentos' => 'nullable|array',
            'items.*.descuentos.*.cod_tipo' => ['nullable', 'string', new SunatCatalog('53')],
            'items.*.descuentos.*.monto_base' => 'nullable|numeric|min:0',
            'items.*.descuentos.*.factor' => 'nullable|numeric|min:0|max:1',
            'items.*.descuentos.*.porcentaje' => 'nullable|numeric|min:0|max:100',
            'items.*.descuentos.*.monto' => 'nullable|numeric|min:0',

            // Leyendas — Cat 52
            'leyenda' => 'nullable|string|max:500',
            'leyendas' => 'nullable|array',
            'leyendas.*.code' => ['required_with:leyendas', 'string', new SunatCatalog('52')],
            'leyendas.*.value' => 'required_with:leyendas|string|max:500',

            // Guías
            'guias' => 'nullable|array',
            'guias.*.tipo_doc' => ['required_with:guias', 'string', new SunatCatalog('01')],
            'guias.*.nro_doc' => 'required_with:guias|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validarCorrelativoManual($v, DebitNote::class);

            // NRUS solo puede emitir notas de débito contra BOLETAS (tipo 03).
            $tenant = $this->get('tenant');
            if ($tenant && $tenant->tax_regime === 'nrus' && $this->input('doc_afectado_tipo') !== '03') {
                $v->errors()->add(
                    'doc_afectado_tipo',
                    'Los contribuyentes del régimen NRUS solo pueden emitir notas de débito contra boletas de venta (tipo 03).'
                );
            }
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
