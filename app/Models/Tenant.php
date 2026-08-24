<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
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
        'obligado_codigo_producto',
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
            'obligado_codigo_producto' => 'boolean',
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
            'client_id' => $this->sire_client_id ?? $this->client_id,
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

        // El dueño (user_id) queda registrado como owner en el pivote tenant_user,
        // para que el RBAC del panel lea sus permisos igual que el backfill inicial.
        static::created(function (Tenant $tenant) {
            if ($tenant->user_id) {
                $tenant->miembros()->syncWithoutDetaching([
                    $tenant->user_id => [
                        'role' => TenantMembership::ROLE_OWNER,
                        'is_active' => true,
                    ],
                ]);
            }
        });

        // Al reasignar el dueño (user_id) desde el panel admin, registrar la membresía owner.
        static::updated(function (Tenant $tenant) {
            if ($tenant->wasChanged('user_id') && $tenant->user_id) {
                $tenant->miembros()->syncWithoutDetaching([
                    $tenant->user_id => [
                        'role' => TenantMembership::ROLE_OWNER,
                        'is_active' => true,
                    ],
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Usuarios miembros de esta empresa (dueño + cajeros) vía el pivote tenant_user. */
    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->using(TenantMembership::class)
            ->withPivot(['role', 'abilities', 'is_active'])
            ->withTimestamps();
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
        $paths = array_filter([
            $this->certificate_path,
            'certificates/'.$this->ruc.'/cert.pem',
            'tenants/'.$this->getKey().'/certs/cert.pem',
        ]);

        foreach ($paths as $path) {
            if (str_starts_with($path, '/') || str_contains($path, ':')) {
                if (is_file($path) && is_readable($path)) {
                    return file_get_contents($path) ?: null;
                }

                continue;
            }

            $disk = Storage::disk('local');
            if ($disk->exists($path)) {
                return $disk->get($path);
            }
        }

        return null;
    }
}
