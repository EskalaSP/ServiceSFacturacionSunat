<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Código de Producto SUNAT (UNSPSC v14 — Catálogo N.° 25).
 *
 * Tabla de referencia estática. La PK es el código de 8 dígitos.
 */
class UnspscCode extends Model
{
    protected $table = 'unspsc_codes';

    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['codigo', 'descripcion', 'clase'];

    /**
     * ¿Existe el código en el catálogo UNSPSC?
     */
    public static function existe(string $codigo): bool
    {
        return static::whereKey($codigo)->exists();
    }
}
