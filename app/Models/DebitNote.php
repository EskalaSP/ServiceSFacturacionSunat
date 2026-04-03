<?php

namespace App\Models;

use App\Contracts\Documentable;
use App\Models\Traits\HasDocumentFields;
use App\Models\Traits\HasSunatIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebitNote extends Model implements Documentable
{
    use HasDocumentFields, HasSunatIntegration;

    protected $fillable = [
        'tenant_id', 'client_id', 'sucursal_id', 'serie', 'correlativo', 'cod_local',
        'fecha_emision', 'tipo_moneda', 'client_tipo_doc', 'client_num_doc',
        'client_razon_social', 'client_direccion',
        'doc_afectado_tipo', 'doc_afectado_serie', 'doc_afectado_correlativo',
        'cod_motivo', 'des_motivo',
        'mto_oper_gravadas', 'mto_oper_exoneradas', 'mto_oper_inafectas',
        'mto_oper_gratuitas', 'mto_igv', 'mto_isc', 'mto_icbper',
        'total_impuestos', 'valor_venta', 'sub_total', 'mto_imp_venta',
        'total_anticipos', 'total_descuentos', 'leyenda', 'observacion', 'guias',
        'xml_path', 'cdr_path', 'pdf_path', 'hash_cpe',
        'sunat_status', 'sunat_code', 'sunat_description', 'sunat_notes',
        'ticket', 'sent_at',
    ];

    protected function casts(): array
    {
        return array_merge($this->sharedCasts(), [
            'guias' => 'array',
        ]);
    }

    public function getTipoDocumento(): string
    {
        return '08';
    }

    public function items(): HasMany
    {
        return $this->hasMany(DebitNoteItem::class);
    }
}
