<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'price_monthly',
        'price_yearly',
        'limits',
        'features',
        'is_unlimited',
        'duration_days',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_unlimited' => 'boolean',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getLimit(string $key, mixed $default = 0): mixed
    {
        return $this->limits[$key] ?? $default;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Un plan es ilimitado si tiene la bandera explícita o si su límite de
     * documentos SUNAT usa el sentinel -1 (compatibilidad con planes previos).
     */
    public function isUnlimited(): bool
    {
        return (bool) $this->is_unlimited
            || (int) $this->getLimit('documents_month', 0) === -1;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
