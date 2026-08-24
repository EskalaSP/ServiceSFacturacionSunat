<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de usuarios del panel administrativo (roles predefinidos).
 * Protegido por el gate 'manage-users' (super_admin y admin).
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarios = User::query()
            ->whereNotNull('role')
            ->latest()
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'role_label' => $u->roleLabel(),
                'is_active' => (bool) $u->is_active,
                'es_actual' => $u->id === $request->user()->id,
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/usuarios/index', [
            'usuarios' => $usuarios,
            'roles' => User::rolesAsignables(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/usuarios/form', [
            'usuario' => null,
            'roles' => User::rolesAsignables(),
            'empresas' => $this->empresasOpciones(),
            'modo' => 'crear',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request, null);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
            // Compatibilidad: is_admin true para roles con capacidad de gestión.
            'is_admin' => in_array($data['role'], [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN], true),
        ]);

        $this->asignarEmpresaSiCliente($user, $data);

        $msg = $data['role'] === User::ROLE_CLIENTE
            ? 'Cliente creado. Ya puede iniciar sesión y emitir desde el panel.'
            : 'Usuario creado.';

        return redirect()->route('admin.usuarios.index')->with('success', $msg);
    }

    public function edit(User $usuario): Response
    {
        return Inertia::render('admin/usuarios/form', [
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'role' => $usuario->role,
                'is_active' => (bool) $usuario->is_active,
                'empresa_id' => Tenant::where('user_id', $usuario->id)->value('id'),
            ],
            'roles' => User::rolesAsignables(),
            'empresas' => $this->empresasOpciones(),
            'modo' => 'editar',
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $data = $this->validar($request, $usuario);

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->role = $data['role'];
        $usuario->is_active = $data['is_active'] ?? $usuario->is_active;
        $usuario->is_admin = in_array($data['role'], [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN], true);
        if (! empty($data['password'])) {
            $usuario->password = Hash::make($data['password']);
        }
        $usuario->save();

        $this->asignarEmpresaSiCliente($usuario, $data);

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario actualizado.');
    }

    /** Empresas para el selector de dueño (cuando el rol es cliente). */
    private function empresasOpciones(): array
    {
        return Tenant::orderBy('razon_social')->get(['id', 'ruc', 'razon_social'])
            ->map(fn (Tenant $t) => ['id' => $t->id, 'label' => "{$t->ruc} — {$t->razon_social}"])
            ->all();
    }

    /** Si el usuario es cliente y se eligió empresa, lo registra como dueño (owner). */
    private function asignarEmpresaSiCliente(User $user, array $data): void
    {
        if (($data['role'] ?? null) !== User::ROLE_CLIENTE || empty($data['empresa_id'])) {
            return;
        }

        $tenant = Tenant::find($data['empresa_id']);
        if (! $tenant) {
            return;
        }

        $tenant->miembros()->syncWithoutDetaching([
            $user->id => ['role' => TenantMembership::ROLE_OWNER, 'is_active' => true],
        ]);

        if (! $tenant->user_id) {
            $tenant->update(['user_id' => $user->id]);
        }
    }

    public function toggle(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $usuario->update(['is_active' => ! $usuario->is_active]);

        return back()->with('success', 'Estado del usuario actualizado.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        // Solo un super admin puede eliminar usuarios.
        if (! $request->user()->canDelete()) {
            abort(403, 'Solo un super administrador puede eliminar usuarios.');
        }

        $usuario->delete();

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario eliminado.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validar(Request $request, ?User $usuario): array
    {
        $creando = $usuario === null;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'password' => $creando
                ? ['required', 'string', PasswordPolicy::rule()]
                : ['nullable', 'string', PasswordPolicy::rule()],
            'role' => ['required', Rule::in(array_keys(User::rolesAsignables()))],
            'empresa_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
