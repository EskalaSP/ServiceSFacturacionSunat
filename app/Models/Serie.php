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
        'factura'             => '01',
        'boleta'              => '03',
        'nota_credito'        => '07',
        'nota_debito'         => '08',
        'guia_remision'       => '09',  // Guía Remitente
        'guia_transportista'  => '31',  // Guía Transportista
        'retencion'           => '20',
        'percepcion'          => '40',
    ];

    // Mapeo inverso: código → nombre amigable
    public const TIPOS_NOMBRE = [
        '01' => 'factura',
        '03' => 'boleta',
        '07' => 'nota_credito',
        '08' => 'nota_debito',
        '09' => 'guia_remision',
        '31' => 'guia_transportista',
        '20' => 'retencion',
        '40' => 'percepcion',
    ];

    // Prefijos válidos por tipo de documento (según Cat. 61 SUNAT)
    public const PREFIJOS = [
        '01' => ['F'],        // Factura: F001, F002...
        '03' => ['B'],        // Boleta: B001, B002...
        '07' => ['F', 'B'],   // NC: FC01, BC01...
        '08' => ['F', 'B'],   // ND: FD01, BD01...
        '09' => ['T'],        // Guía Remitente: T001, T002... (formato: [T][A-Z0-9]{3})
        '31' => ['V'],        // Guía Transportista: V001, V002... (formato: [V][A-Z0-9]{3})
        '20' => ['R'],        // Retención: R001...
        '40' => ['P'],        // Percepción: P001...
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

    /**
     * Resuelve el correlativo del próximo comprobante de esta serie.
     *
     * - $manual === null → autoincremental (comportamiento por defecto según
     *   la configuración de la serie).
     * - $manual entero   → modo manual: usa ese número y adelanta el puntero de
     *   la serie solo si es mayor, para que los siguientes automáticos no
     *   colisionen. No retrocede el puntero (permite rellenar huecos).
     *
     * Todo ocurre dentro de una transacción con lock para evitar condiciones de
     * carrera entre emisiones concurrentes de la misma serie.
     */
    public function resolveCorrelativo(?int $manual = null): int
    {
        return DB::transaction(function () use ($manual) {
            $serie = self::where('id', $this->id)->lockForUpdate()->first();

            if ($manual === null) {
                $serie->increment('correlativo');

                return (int) $serie->correlativo;
            }

            if ($manual > (int) $serie->correlativo) {
                $serie->update(['correlativo' => $manual]);
            }

            return $manual;
        });
    }

    public function nextCorrelativo(): int
    {
        return $this->resolveCorrelativo(null);
    }
}
