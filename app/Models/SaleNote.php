<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SaleNote extends InternalDocument
{
    protected static function booted(): void
    {
        static::addGlobalScope('type', fn (Builder $q) => $q->where('type', 'sale_note'));
        static::creating(fn (self $model) => $model->type = 'sale_note');
    }

    public function getTipoDocumento(): string
    {
        return 'NV';
    }

    public static array $validStatuses = ['emitida', 'anulada'];
}
