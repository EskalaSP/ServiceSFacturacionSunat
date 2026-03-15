<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Quotation extends InternalDocument
{
    protected static function booted(): void
    {
        static::addGlobalScope('type', fn (Builder $q) => $q->where('type', 'quotation'));
        static::creating(fn (self $model) => $model->type = 'quotation');
    }

    public function getTipoDocumento(): string
    {
        return 'COT';
    }

    public static array $validStatuses = ['vigente', 'aceptada', 'rechazada', 'vencida'];
}
