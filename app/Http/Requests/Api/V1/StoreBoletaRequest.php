<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesManualCorrelativo;
use App\Models\Boleta;
use App\Rules\SunatCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBoletaRequest extends FormRequest
{
    use ValidatesManualCorrelativo;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serie' => ['required', 'string', 'size:4', 'regex:/^B[A-Z0-9]{3}$/'],
            // Correlativo opcional: si se omite, se autoincrementa según la serie.
            'correlativo' => 'nullable|integer|min:1',
            'cod_local' => 'nullable|string|size:4',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'tipo_operacion' => ['nullable', 'string', new SunatCatalog('51')],
            'tipo_moneda' => 'nullable|string|in:PEN,USD,EUR',
            'forma_pago' => 'nullable|string|in:Contado,Credito',

            // Cliente — Cat 06, longitudes Formato 1.3.4
            'cliente.tipo_doc' => ['required', 'string', new SunatCatalog('06')],
            'cliente.num_doc' => ['required', 'string', 'max:15'],
            'cliente.razon_social' => 'required|string|max:1500',
            'cliente.direccion' => 'nullable|string|max:500',
            'cliente.email' => 'nullable|email',
            'cliente.telefono' => 'nullable|string|max:20',

            // Montos calculados
            'mto_oper_gravadas' => 'nullable|numeric|min:0',
            'mto_oper_exoneradas' => 'nullable|numeric|min:0',
            'mto_oper_inafectas' => 'nullable|numeric|min:0',
            'mto_oper_exportacion' => 'nullable|numeric|min:0',
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
            'items.*.porcentaje_isc' => 'nullable|numeric|min:0',
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

            // Cuotas
            'cuotas' => 'nullable|array',
            'cuotas.*.monto' => 'required_with:cuotas|numeric|gt:0',
            'cuotas.*.fecha_pago' => 'required_with:cuotas|date',

            // Guías
            'guias' => 'nullable|array',
            'guias.*.tipo_doc' => ['required_with:guias', 'string', new SunatCatalog('01')],
            'guias.*.nro_doc' => 'required_with:guias|string',

            // Detracción — Cat 54, Cat 59
            'detraccion' => 'nullable|array',
            'detraccion.cod_bien' => ['required_with:detraccion', 'string', new SunatCatalog('54')],
            'detraccion.porcentaje' => 'required_with:detraccion|numeric|min:0|max:100',
            'detraccion.cta_banco' => 'required_with:detraccion|string|max:20',
            'detraccion.cod_medio_pago' => ['nullable', 'string', new SunatCatalog('59')],
            'detraccion.monto' => 'required_with:detraccion|numeric|min:0',

            // Percepción — Cat 22
            'percepcion' => 'nullable|array',
            'percepcion.cod_regimen' => ['required_with:percepcion', 'string', new SunatCatalog('22')],
            'percepcion.porcentaje' => 'required_with:percepcion|numeric|min:0|max:100',
            'percepcion.monto' => 'required_with:percepcion|numeric|min:0',
            'percepcion.base' => 'required_with:percepcion|numeric|min:0',

            // Anticipos — Cat 12
            'anticipos' => 'nullable|array',
            'anticipos.*.tipo_doc' => ['required_with:anticipos', 'string', new SunatCatalog('12')],
            'anticipos.*.serie' => 'required_with:anticipos|string|max:4',
            'anticipos.*.correlativo' => 'required_with:anticipos|string|max:8',
            'anticipos.*.monto' => 'required_with:anticipos|numeric|min:0',

            'total_anticipos' => 'nullable|numeric|min:0',
            'total_descuentos' => 'nullable|numeric|min:0',

            // Descuentos globales — Cat 53
            'descuentos_globales' => 'nullable|array',
            'descuentos_globales.*.cod_tipo' => ['required_with:descuentos_globales', 'string', new SunatCatalog('53')],
            'descuentos_globales.*.porcentaje' => 'nullable|numeric|min:0|max:1',
            'descuentos_globales.*.monto' => 'required_with:descuentos_globales|numeric|min:0',
            'descuentos_globales.*.monto_base' => 'nullable|numeric|min:0',

            'extras' => 'nullable|array',
            'observacion' => 'nullable|string|max:500',

            // Pagos
            'pagos' => 'nullable|array',
            'pagos.*.metodo' => 'required_with:pagos|string',
            'pagos.*.monto' => 'required_with:pagos|numeric|gt:0',
            'pagos.*.referencia' => 'nullable|string|max:100',
            'pagos.*.monto_recibido' => 'nullable|numeric|min:0',
            'pagos.*.notas' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'serie.regex' => 'La serie de boleta debe empezar con B seguida de 3 caracteres alfanuméricos (ej: B001, BA01, BABC).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validarCorrelativoManual($v, Boleta::class);

            $items = $this->input('items', []);
            $total = 0;

            foreach ($items as $item) {
                $qty = (float) ($item['cantidad'] ?? 0);
                $price = (float) ($item['precio_unitario'] ?? 0);
                $total += $qty * $price;
            }

            $tipoDoc = $this->input('cliente.tipo_doc', '0');

            // Boleta > S/700: doc identidad obligatorio
            if ($total > 700 && $tipoDoc === '0') {
                $v->errors()->add(
                    'cliente.tipo_doc',
                    'Para boletas mayores a S/ 700.00 es obligatorio consignar el documento de identidad del cliente (DNI, RUC, CE, etc.).'
                );
            }

            // Validar consistencia tipo_doc vs num_doc
            $numDoc = $this->input('cliente.num_doc', '');

            if ($tipoDoc === '1' && strlen($numDoc) !== 8) {
                $v->errors()->add('cliente.num_doc', 'El DNI debe tener exactamente 8 dígitos.');
            }

            if ($tipoDoc === '6' && strlen($numDoc) !== 11) {
                $v->errors()->add('cliente.num_doc', 'El RUC debe tener exactamente 11 dígitos.');
            }

            // Si forma_pago es Crédito, cuotas son obligatorias
            if ($this->input('forma_pago') === 'Credito' && empty($this->input('cuotas'))) {
                $v->errors()->add('cuotas', 'Las cuotas son obligatorias cuando la forma de pago es Crédito.');
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
