<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'payment_gateway',
        'gateway_customer_id',
        'gateway_card_id',
        'card_last_four',
        'card_brand',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->current_period_end && $this->current_period_end->isPast() && $this->status !== 'active');
    }

    public function daysUntilRenewal(): ?int
    {
        if (! $this->current_period_end) {
            return null;
        }

        return (int) now()->diffInDays($this->current_period_end, false);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trialing']);
    }

    public function scopeDueForRenewal($query)
    {
        return $query->where('status', 'active')
            ->where('current_period_end', '<=', now())
            ->whereNull('cancelled_at');
    }

    public function scopeTrialExpiring($query, int $daysAhead = 3)
    {
        return $query->where('status', 'trialing')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($daysAhead)]);
    }
}
