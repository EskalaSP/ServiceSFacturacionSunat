<?php

namespace App\Sire\Models;

use App\Models\Tenant;
use App\Sire\Enums\FasePeriodo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SirePeriodo extends Model
{
    protected $table = 'sire_periodos';

    protected $fillable = [
        'tenant_id',
        'per_tributario',
        'cod_libro',
        'fase',
        'cod_estado',
        'des_estado',
        'fec_cierre',
    ];

    protected function casts(): array
    {
        return [
            'fec_cierre' => 'datetime',
            'fase'       => FasePeriodo::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(SireComprobante::class);
    }
}
