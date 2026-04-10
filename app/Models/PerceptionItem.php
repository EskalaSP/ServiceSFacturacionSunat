<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerceptionItem extends Model
{
    protected $fillable = [
        'perception_id', 'tipo_doc', 'num_doc', 'fecha_emision_doc',
        'imp_total', 'moneda', 'cobros', 'fecha_percepcion',
        'imp_percibido', 'imp_cobrar', 'tipo_cambio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision_doc' => 'date',
            'fecha_percepcion' => 'date',
            'imp_total' => 'decimal:2',
            'imp_percibido' => 'decimal:2',
            'imp_cobrar' => 'decimal:2',
            'cobros' => 'array',
            'tipo_cambio' => 'array',
        ];
    }

    public function perception(): BelongsTo
    {
        return $this->belongsTo(Perception::class);
    }
}
