<?php

namespace App\Sire\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SireReconciliationLog extends Model
{
    protected $table = 'sire_reconciliation_logs';

    protected $fillable = [
        'tenant_id',
        'per_tributario',
        'total_sunat',
        'total_local',
        'match_count',
        'only_sunat_count',
        'only_local_count',
        'diff_amount_count',
        'diff_total_monto',
        'details',
        'run_at',
    ];

    protected function casts(): array
    {
        return [
            'run_at'            => 'datetime',
            'details'           => 'array',
            'diff_total_monto'  => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
