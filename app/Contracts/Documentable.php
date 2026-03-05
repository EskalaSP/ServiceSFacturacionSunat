<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface Documentable
{
    public function getTipoDocumento(): string;

    public function getNumeroCompletoAttribute(): string;

    public function items(): HasMany;
}
