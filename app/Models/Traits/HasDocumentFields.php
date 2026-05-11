<?php

namespace App\Models\Traits;

use App\Models\Client;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

trait HasDocumentFields
{
    use BelongsToTenant, SoftDeletes;

    public function getNumeroCompletoAttribute(): string
    {
        return $this->serie . '-' . str_pad((string) $this->correlativo, 6, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('sunat_status', $status);
    }

    public function scopeFechas(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('fecha_emision', [
            $desde . ' 00:00:00',
            $hasta . ' 23:59:59',
        ]);
    }

    protected function sharedCasts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_vencimiento' => 'date',
            'correlativo' => 'integer',
            'mto_oper_gravadas' => 'decimal:2',
            'mto_oper_exoneradas' => 'decimal:2',
            'mto_oper_inafectas' => 'decimal:2',
            'mto_oper_gratuitas' => 'decimal:2',
            'mto_igv' => 'decimal:2',
            'mto_isc' => 'decimal:2',
            'mto_icbper' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'valor_venta' => 'decimal:2',
            'sub_total' => 'decimal:2',
            'mto_imp_venta' => 'decimal:2',
            'total_anticipos' => 'decimal:2',
            'total_descuentos' => 'decimal:2',
            'sunat_notes' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    protected function sharedFillable(): array
    {
        return [
            'tenant_id',
            'sucursal_id',
            'client_id',
            'serie',
            'correlativo',
            'cod_local',
            'fecha_emision',
            'fecha_vencimiento',
            'tipo_operacion',
            'tipo_moneda',
            'forma_pago',
            'client_tipo_doc',
            'client_num_doc',
            'client_razon_social',
            'client_direccion',
            'mto_oper_gravadas',
            'mto_oper_exoneradas',
            'mto_oper_inafectas',
            'mto_oper_gratuitas',
            'mto_igv',
            'mto_isc',
            'mto_icbper',
            'total_impuestos',
            'valor_venta',
            'sub_total',
            'mto_imp_venta',
            'total_anticipos',
            'total_descuentos',
            'leyenda',
            'observacion',
            'xml_path',
            'cdr_path',
            'pdf_path',
            'hash_cpe',
            'sunat_status',
            'sunat_code',
            'sunat_description',
            'sunat_notes',
            'ticket',
            'sent_at',
        ];
    }
}
