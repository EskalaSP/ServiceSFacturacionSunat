<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Serie extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tipo_documento',
        'serie',
        'correlativo',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'is_active' => 'boolean',
        ];
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
