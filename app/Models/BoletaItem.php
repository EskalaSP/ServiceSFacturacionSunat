<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoletaItem extends Model
{
    protected $fillable = [
        'boleta_id', 'codigo', 'descripcion', 'unidad', 'cantidad',
        'mto_valor_unitario', 'mto_valor_venta', 'mto_base_igv',
        'porcentaje_igv', 'igv', 'tip_afe_igv', 'isc', 'icbper',
        'total_impuestos', 'mto_precio_unitario', 'descuento', 'descuentos',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'mto_valor_unitario' => 'decimal:4',
            'mto_valor_venta' => 'decimal:2',
            'mto_base_igv' => 'decimal:2',
            'porcentaje_igv' => 'decimal:2',
            'igv' => 'decimal:2',
            'isc' => 'decimal:2',
            'icbper' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'mto_precio_unitario' => 'decimal:4',
            'descuento' => 'decimal:2',
            'descuentos' => 'array',
        ];
    }

    public function boleta(): BelongsTo
    {
        return $this->belongsTo(Boleta::class);
    }
}
