<?php

namespace App\Sire\Models;

use App\Models\Tenant;
use App\Sire\Enums\EstadoTicket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SireTicket extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'sire_tickets';

    protected static function newFactory()
    {
        return \Database\Factories\SireTicketFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'num_ticket',
        'per_tributario',
        'cod_proceso',
        'des_proceso',
        'cod_estado_proceso',
        'des_estado_proceso',
        'nom_archivo_importacion',
        'nom_archivo_reporte',
        'cod_tipo_archivo_reporte',
        'cnt_filas_validadas',
        'cnt_cp_informados',
        'cnt_cp_error',
        'archivo_local_path',
        'poll_attempts',
        'last_polled_at',
        'finished_at',
        'sunat_request_payload',
        'sunat_last_response',
    ];

    protected function casts(): array
    {
        return [
            'last_polled_at'        => 'datetime',
            'finished_at'           => 'datetime',
            'sunat_request_payload' => 'array',
            'sunat_last_response'   => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(SireComprobante::class, 'origen_ticket_id');
    }

    public function estadoEnum(): ?EstadoTicket
    {
        return $this->cod_estado_proceso
            ? EstadoTicket::tryFrom($this->cod_estado_proceso)
            : null;
    }

    public function isFinal(): bool
    {
        return $this->estadoEnum()?->isFinal() ?? false;
    }

    public function isSuccess(): bool
    {
        return $this->estadoEnum()?->isSuccess() ?? false;
    }
}
