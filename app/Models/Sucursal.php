<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'cod_local',
        'direccion',
        'ubigeo',
        'telefono',
        'email',
        'is_principal',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_principal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function boletas(): HasMany
    {
        return $this->hasMany(Boleta::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(DebitNote::class);
    }

    public function dispatchGuides(): HasMany
    {
        return $this->hasMany(DispatchGuide::class);
    }
}
