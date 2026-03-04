<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'tipo_documento',
        'serie',
        'correlativo',
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
        'doc_afectado_tipo',
        'doc_afectado_serie',
        'doc_afectado_correlativo',
        'cod_motivo',
        'des_motivo',
        'cuotas',
        'detraccion',
        'percepcion',
        'anticipos',
        'descuentos_globales',
        'guias',
        'extras',
        'cod_local',
        'xml_content',
        'cdr_content',
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

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
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
            'cuotas' => 'array',
            'detraccion' => 'array',
            'percepcion' => 'array',
            'anticipos' => 'array',
            'descuentos_globales' => 'array',
            'guias' => 'array',
            'extras' => 'array',
            'sunat_notes' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function getNumeroCompletoAttribute(): string
    {
        return $this->serie . '-' . $this->correlativo;
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('sunat_status', $status);
    }

    public function scopeTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo_documento', $tipo);
    }

    public function scopeFechas(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('fecha_emision', [$desde, $hasta]);
    }
}
