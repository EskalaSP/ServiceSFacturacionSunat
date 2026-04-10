<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Perception extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'serie', 'correlativo', 'cod_local', 'fecha_emision',
        'cliente_tipo_doc', 'cliente_num_doc', 'cliente_razon_social', 'cliente_direccion',
        'regimen', 'tasa', 'imp_percibido', 'imp_cobrado', 'observacion',
        'xml_path', 'cdr_path', 'pdf_path', 'hash_cpe',
        'sunat_status', 'sunat_code', 'sunat_description', 'sunat_notes',
        'ticket', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'correlativo' => 'integer',
            'tasa' => 'decimal:2',
            'imp_percibido' => 'decimal:2',
            'imp_cobrado' => 'decimal:2',
            'sunat_notes' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PerceptionItem::class);
    }

    public function getNumeroCompletoAttribute(): string
    {
        return $this->serie . '-' . str_pad((string) $this->correlativo, 8, '0', STR_PAD_LEFT);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('sunat_status', $status);
    }
}
