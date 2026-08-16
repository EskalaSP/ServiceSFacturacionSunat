<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'roles' => User::ROLES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/usuarios/form', [
            'usuario' => null,
            'roles' => User::ROLES,
            'modo' => 'crear',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request, null);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
            // Compatibilidad: is_admin true para roles con capacidad de gestión.
            'is_admin' => in_array($data['role'], [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN], true),
        ]);

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado.');
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
            ],
            'roles' => User::ROLES,
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

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario actualizado.');
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
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
