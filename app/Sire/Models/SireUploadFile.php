<?php

namespace App\Sire\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SireUploadFile extends Model
{
    protected $table = 'sire_upload_files';

    protected $fillable = [
        'tenant_id',
        'sire_ticket_id',
        'per_tributario',
        'cod_proceso',
        'nom_archivo',
        'local_path',
        'size_bytes',
        'sha256',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'size_bytes'  => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SireTicket::class, 'sire_ticket_id');
    }
}
