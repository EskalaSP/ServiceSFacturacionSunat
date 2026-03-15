<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'identifier', 'correlativo', 'fecha_referencia',
        'fecha_envio', 'total_documentos', 'tipo', 'document_ids',
        'xml_path', 'cdr_path', 'ticket', 'sunat_status', 'sunat_code',
        'sunat_description', 'sunat_notes',
    ];

    protected function casts(): array
    {
        return [
            'fecha_referencia' => 'date',
            'fecha_envio' => 'date',
            'document_ids' => 'array',
            'sunat_notes' => 'array',
        ];
    }
}
