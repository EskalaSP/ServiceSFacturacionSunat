<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Serie extends Model
{
    use BelongsToTenant, HasFactory;

    // Mapeo: nombre amigable → código SUNAT
    public const TIPOS = [
        'factura'        => '01',
        'boleta'         => '03',
        'nota_credito'   => '07',
        'nota_debito'    => '08',
        'guia_remision'  => '09',
        'retencion'      => '20',
        'percepcion'     => '40',
    ];

    // Mapeo inverso: código → nombre amigable
    public const TIPOS_NOMBRE = [
        '01' => 'factura',
        '03' => 'boleta',
        '07' => 'nota_credito',
        '08' => 'nota_debito',
        '09' => 'guia_remision',
        '20' => 'retencion',
        '40' => 'percepcion',
    ];

    // Prefijos válidos por tipo de documento
    public const PREFIJOS = [
        '01' => ['F'],           // Factura: F001, F002...
        '03' => ['B'],           // Boleta: B001, B002...
        '07' => ['F', 'B'],     // NC: FC01, BC01...
        '08' => ['F', 'B'],     // ND: FD01, BD01...
        '09' => ['T', 'V'],     // Guía: T001, V001...
        '20' => ['R'],           // Retención: R001...
        '40' => ['P'],           // Percepción: P001...
    ];

    protected $fillable = [
        'tenant_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'sucursal_nombre',
        'sucursal_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function nextCorrelativo(): int
    {
        return DB::transaction(function () {
            $serie = self::where('id', $this->id)->lockForUpdate()->first();
            $serie->increment('correlativo');

            return $serie->correlativo;
        });
    }
}
