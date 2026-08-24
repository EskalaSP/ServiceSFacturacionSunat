<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
        'is_active',
    ];

    // ── Roles del panel ──────────────────────────────────────────────
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SOPORTE = 'soporte';

    public const ROLE_LECTURA = 'lectura';

    /** Usuario "cliente": dueño de una empresa. SIN acceso al panel admin (emite en /sunat). */
    public const ROLE_CLIENTE = 'cliente';

    /** Roles que tienen acceso al panel administrativo, con su etiqueta. */
    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super administrador',
        self::ROLE_ADMIN => 'Administrador',
        self::ROLE_SOPORTE => 'Soporte',
        self::ROLE_LECTURA => 'Solo lectura',
    ];

    /**
     * Roles asignables desde el panel: los internos + "cliente" (dueño de empresa).
     * El rol cliente NO está en ROLES a propósito, para que hasPanelAccess() sea false.
     *
     * @return array<string,string>
     */
    public static function rolesAsignables(): array
    {
        return self::ROLES + [self::ROLE_CLIENTE => 'Cliente (dueño de empresa)'];
    }

    public function tenants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Empresas donde el usuario es miembro (dueño o cajero) vía el pivote tenant_user.
     * Distinto de tenants(): aquí importa la MEMBRESÍA, no la propiedad.
     */
    public function empresas(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->using(TenantMembership::class)
            ->withPivot(['role', 'abilities', 'is_active'])
            ->withTimestamps();
    }

    /** Membresía del usuario en una empresa concreta, o null si no pertenece. */
    public function membershipFor(Tenant $tenant): ?TenantMembership
    {
        $empresa = $this->empresas()->firstWhere('tenants.id', $tenant->getKey());

        /** @var TenantMembership|null $pivot */
        $pivot = $empresa?->pivot;

        return $pivot;
    }

    /**
     * ¿Puede el usuario realizar `$ability` en `$tenant`?
     * Super admin: siempre. Resto: según su membresía (owner = todo; cajero = sus abilities).
     */
    public function puede(string $ability, Tenant $tenant): bool
    {
        // Solo bloquea si la cuenta está explícitamente desactivada.
        if ($this->is_active === false) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->membershipFor($tenant)?->permite($ability) ?? false;
    }

    /** ¿Puede entrar al panel administrativo? (cualquiera de los 4 roles) */
    public function hasPanelAccess(): bool
    {
        return $this->is_active && array_key_exists((string) $this->role, self::ROLES);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Gestión de usuarios: super admin y admin. */
    public function canManageUsers(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    /** Crear/editar/eliminar empresas, planes, series, sucursales. */
    public function canWrite(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    /** Eliminar (empresas, usuarios): solo super admin. */
    public function canDelete(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Reenviar comprobantes a SUNAT: super admin, admin, soporte. */
    public function canResend(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_SOPORTE], true);
    }

    public function roleLabel(): string
    {
        return self::rolesAsignables()[$this->role] ?? '—';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Obtener los atributos que deben ser casteados.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
