<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    const METODOS = [
        'efectivo',
        'yape',
        'plin',
        'transferencia',
        'tarjeta',
        'cheque',
        'otro',
    ];

    protected $fillable = [
        'tenant_id',
        'payable_type',
        'payable_id',
        'metodo',
        'monto',
        'referencia',
        'monto_recibido',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_recibido' => 'decimal:2',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
