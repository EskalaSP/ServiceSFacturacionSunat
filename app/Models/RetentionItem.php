<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionItem extends Model
{
    protected $fillable = [
        'retention_id', 'tipo_doc', 'num_doc', 'fecha_emision_doc',
        'imp_total', 'moneda', 'pagos', 'fecha_retencion',
        'imp_retenido', 'imp_pagar', 'tipo_cambio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision_doc' => 'date',
            'fecha_retencion' => 'date',
            'imp_total' => 'decimal:2',
            'imp_retenido' => 'decimal:2',
            'imp_pagar' => 'decimal:2',
            'pagos' => 'array',
            'tipo_cambio' => 'array',
        ];
    }

    public function retention(): BelongsTo
    {
        return $this->belongsTo(Retention::class);
    }
}
