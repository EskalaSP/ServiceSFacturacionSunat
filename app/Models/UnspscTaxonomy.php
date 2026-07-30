<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nivel de la jerarquía UNSPSC (segmento/familia/clase). Ver UnspscCode para productos.
 */
class UnspscTaxonomy extends Model
{
    protected $table = 'unspsc_taxonomy';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['nivel', 'codigo', 'nombre', 'parent'];
}
