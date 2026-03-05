<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class VoidedDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'identifier', 'correlativo', 'fecha_generacion',
        'fecha_comunicacion', 'total_documentos', 'detalles',
        'xml_path', 'ticket', 'sunat_status', 'sunat_code',
        'sunat_description', 'sunat_notes',
    ];

    protected function casts(): array
    {
        return [
            'fecha_generacion' => 'date',
            'fecha_comunicacion' => 'date',
            'detalles' => 'array',
            'sunat_notes' => 'array',
        ];
    }
}
