<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tipo_documento',
        'numero_documento',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'email',
        'telefono',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
