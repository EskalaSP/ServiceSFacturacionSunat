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

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'ubigeo',
        'departamento',
        'provincia',
        'distrito',
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
        'max_documents_month',
        'is_active',
        'user_id',
    ];

    protected $hidden = [
        'sol_pass',
        'client_secret',
        'certificate_password',
        'api_secret',
    ];

    protected function casts(): array
    {
        return [
            'sol_user' => 'encrypted',
            'sol_pass' => 'encrypted',
            'client_secret' => 'encrypted',
            'certificate_password' => 'encrypted',
            'is_active' => 'boolean',
            'max_documents_month' => 'integer',
        ];
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

    public function getCertificateContent(): ?string
    {
        if (! $this->certificate_path || ! file_exists($this->certificate_path)) {
            return null;
        }

        return file_get_contents($this->certificate_path);
    }
}
