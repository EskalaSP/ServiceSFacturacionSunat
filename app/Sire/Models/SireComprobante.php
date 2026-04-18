<?php

namespace App\Sire\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SireComprobante extends Model
{
    protected $table = 'sire_comprobantes';

    protected $fillable = [
        'tenant_id',
        'sire_periodo_id',
        'origen_ticket_id',
        'per_tributario',
        'fase',
        'car_sunat',
        'fec_emision',
        'fec_vencimiento',
        'cod_tipo_cdp',
        'num_serie_cdp',
        'num_cdp',
        'num_doc_proveedor',
        'tipo_doc_proveedor',
        'razon_social_proveedor',
        'cod_moneda',
        'mto_bi_gravada',
        'mto_igv',
        'mto_bi_no_gravada',
        'mto_total',
        'tipo_cambio',
        'cod_inconsistencia',
        'incluido',
        'raw_line',
    ];

    protected function casts(): array
    {
        return [
            'fec_emision'       => 'date',
            'fec_vencimiento'   => 'date',
            'mto_bi_gravada'    => 'decimal:2',
            'mto_igv'           => 'decimal:2',
            'mto_bi_no_gravada' => 'decimal:2',
            'mto_total'         => 'decimal:2',
            'tipo_cambio'       => 'decimal:4',
            'incluido'          => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(SirePeriodo::class, 'sire_periodo_id');
    }

    public function ticketOrigen(): BelongsTo
    {
        return $this->belongsTo(SireTicket::class, 'origen_ticket_id');
    }
}
