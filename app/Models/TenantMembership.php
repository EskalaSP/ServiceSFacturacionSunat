<?php

namespace App\Models;

use App\Support\Rbac\Ability;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membresía de un usuario en una empresa (fila del pivote tenant_user).
 *
 * @property int $tenant_id
 * @property int $user_id
 * @property string $role
 * @property list<string>|null $abilities
 * @property bool $is_active
 */
class TenantMembership extends Pivot
{
    protected $table = 'tenant_user';

    public $incrementing = true;

    public const ROLE_SIMPLE = 'simple';

    public const ROLE_CAJERO = 'cajero';

    /** Acceso completo: ve todo, incluye series, config, SIRE, API, equipo, etc. */
    public const ROLE_COMPLETO = 'completo';

    /** @var array<string,string> */
    public const ROLES = [
        self::ROLE_COMPLETO => 'Completo (acceso extendido)',
        self::ROLE_SIMPLE   => 'Simple (vista restringida)',
        self::ROLE_CAJERO   => 'Cajero',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function esSimple(): bool
    {
        return $this->role === self::ROLE_SIMPLE;
    }

    public function esCompleto(): bool
    {
        return $this->role === self::ROLE_COMPLETO;
    }

    /**
     * ¿Esta membresía habilita el permiso indicado?
     * Completo → todo. Simple → solo permisos base. Cajero → abilities asignadas.
     */
    public function permite(string $ability): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->esCompleto()) {
            return true;
        }

        if ($this->esSimple()) {
            return in_array($ability, Ability::presetSimple(), true);
        }

        return in_array($ability, $this->abilitiesArray(), true);
    }

    /**
     * Permisos como array, sin depender del cast del pivote (attach() no lo aplica al
     * escribir; algunos flujos guardan JSON crudo). Chequeo de seguridad → normaliza siempre.
     *
     * @return list<string>
     */
    public function abilitiesArray(): array
    {
        $abilities = $this->abilities;

        if (is_string($abilities)) {
            $abilities = json_decode($abilities, true);
        }

        return is_array($abilities) ? array_values($abilities) : [];
    }
}
