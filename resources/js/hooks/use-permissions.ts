import { usePage } from '@inertiajs/react';

type Role = 'super_admin' | 'admin' | 'soporte' | 'lectura' | null;

/**
 * Permisos del usuario en sesión según su rol, para mostrar/ocultar acciones
 * en la interfaz. El backend (Gates + middleware panel.write) es la autoridad;
 * esto solo evita mostrar botones que el usuario no puede usar.
 *
 *   - super_admin: todo (escribir, eliminar, gestionar usuarios).
 *   - admin:       escribir y gestionar usuarios; NO eliminar.
 *   - soporte:     reenviar comprobantes; solo lectura del resto.
 *   - lectura:     solo lectura.
 */
export function usePermissions() {
    const { auth } = usePage<{ auth: { user?: { role?: Role; is_admin?: boolean } } }>().props;
    const role = auth?.user?.role ?? null;

    return {
        role,
        isSuperAdmin: role === 'super_admin',
        canWrite: role === 'super_admin' || role === 'admin',
        canDelete: role === 'super_admin',
        canManageUsers: role === 'super_admin' || role === 'admin',
        canResend: role === 'super_admin' || role === 'admin' || role === 'soporte',
    };
}
