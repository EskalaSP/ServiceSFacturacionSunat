<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /** La emisión respeta los límites del plan/suscripción. */
    public const EMISSION_PLAN = 'plan';

    /** La empresa emite sin restricciones, sin depender de un plan. */
    public const EMISSION_UNLIMITED = 'unlimited';

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'ubigeo',
        'departamento',
        'provincia',
        'distrito',
        'telefonos',
        'emails',
        'cuentas_bancarias',
        'billeteras_digitales',
        'mensaje_agradecimiento',
        'mensaje_promocional',
        'sol_user',
        'sol_pass',
        'client_id',
        'client_secret',
        'certificate_path',
        'certificate_password',
        'environment',
        'webhook_url',
        'logo_path',
        'api_key',
        'api_secret',
        'plan',
        'emission_mode',
        'tax_regime',
        'igv_rate_override',
        'nrus_categoria',
        'max_documents_month',
        'documents_this_month',
        'ai_messages_this_month',
        'usage_reset_month',
        'is_active',
        'user_id',
        'sire_enabled',
        'sire_last_period_synced',
        'sire_last_reconciliation_at',
        'sire_client_id',
        'sire_client_secret',
    ];

    protected $hidden = [
        'sol_pass',
        'client_secret',
        'certificate_password',
        'api_secret',
        'sire_client_secret',
    ];

    protected function casts(): array
    {
        return [
            'sol_user' => 'encrypted',
            'sol_pass' => 'encrypted',
            'client_secret' => 'encrypted',
            'certificate_password' => 'encrypted',
            'telefonos' => 'array',
            'emails' => 'array',
            'cuentas_bancarias' => 'array',
            'billeteras_digitales' => 'array',
            'is_active' => 'boolean',
            'max_documents_month' => 'integer',
            'igv_rate_override' => 'decimal:2',
            'nrus_categoria' => 'integer',
            'sire_enabled' => 'boolean',
            'sire_client_secret' => 'encrypted',
            'sire_last_reconciliation_at' => 'datetime',
        ];
    }

    /**
     * Devuelve las credenciales SIRE efectivas, con fallback a las globales del tenant.
     *
     * @return array{client_id: ?string, client_secret: ?string}
     */
    public function sireCredentials(): array
    {
        return [
            'client_id'     => $this->sire_client_id     ?? $this->client_id,
            'client_secret' => $this->sire_client_secret ?? $this->client_secret,
        ];
    }

    public function sirePeriodos(): HasMany
    {
        return $this->hasMany(\App\Sire\Models\SirePeriodo::class);
    }

    public function sireTickets(): HasMany
    {
        return $this->hasMany(\App\Sire\Models\SireTicket::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->api_key)) {
                $tenant->api_key = Str::random(64);
            }
            if (empty($tenant->api_secret)) {
                $tenant->api_secret = hash('sha256', Str::random(64));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function boletas(): HasMany
    {
        return $this->hasMany(Boleta::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(DebitNote::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    public function dispatchGuides(): HasMany
    {
        return $this->hasMany(DispatchGuide::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trialing'])
            ->latestOfMany();
    }

    public function documentsThisMonth(): int
    {
        $month = now()->month;
        $year = now()->year;

        return $this->invoices()->whereMonth('created_at', $month)->whereYear('created_at', $year)->count()
            + $this->boletas()->whereMonth('created_at', $month)->whereYear('created_at', $year)->count()
            + $this->creditNotes()->whereMonth('created_at', $month)->whereYear('created_at', $year)->count()
            + $this->debitNotes()->whereMonth('created_at', $month)->whereYear('created_at', $year)->count();
    }

    public function hasReachedDocumentLimit(): bool
    {
        return $this->documentsThisMonth() >= $this->max_documents_month;
    }

    /**
     * ¿Esta empresa está configurada individualmente como ilimitada?
     * (No considera el switch global — eso lo resuelve EmissionPolicyService.)
     */
    public function hasUnlimitedEmission(): bool
    {
        return $this->emission_mode === self::EMISSION_UNLIMITED;
    }

    public function getCertificateContent(): ?string
    {
        if (! $this->certificate_path || ! file_exists($this->certificate_path)) {
            return null;
        }

        return file_get_contents($this->certificate_path);
    }
}
