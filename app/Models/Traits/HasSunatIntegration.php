<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSunatIntegration
{
    public function scopePendiente(Builder $query): Builder
    {
        return $query->where('sunat_status', 'pendiente');
    }

    public function scopeAceptado(Builder $query): Builder
    {
        return $query->where('sunat_status', 'aceptado');
    }

    public function scopeEnviado(Builder $query): Builder
    {
        return $query->where('sunat_status', 'enviado');
    }
}
