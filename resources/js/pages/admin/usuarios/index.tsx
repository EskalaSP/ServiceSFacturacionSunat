import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Power, ShieldCheck, Trash2, Users } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import { usePermissions } from '@/hooks/use-permissions';
import type { BreadcrumbItem } from '@/types';

type Usuario = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    es_actual: boolean;
    created_at: string | null;
};

type Props = {
    usuarios: Usuario[];
    roles: Record<string, string>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Usuarios', href: '/admin/usuarios' },
];

/** Color del badge según el rol. */
const roleBadge = (role: string) => {
    switch (role) {
        case 'super_admin':
            return 'border-transparent bg-primary/15 text-primary';
        case 'admin':
            return 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
        case 'soporte':
            return 'border-transparent bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
};

export default function UsuariosIndex({ usuarios }: Props) {
    const confirm = useConfirm();
    const perms = usePermissions();

    const toggle = (u: Usuario) =>
        router.post(`/admin/usuarios/${u.id}/toggle`, {}, { preserveScroll: true });

    const eliminar = async (u: Usuario) => {
        if (
            await confirm({
                title: `¿Eliminar a "${u.name}"?`,
                description: 'El usuario perderá el acceso al panel. Esta acción no se puede deshacer.',
                variant: 'danger',
                confirmText: 'Eliminar',
            })
        ) {
            router.delete(`/admin/usuarios/${u.id}`, { preserveScroll: true });
        }
    };

    const columns: ColumnDef<Usuario>[] = [
        {
            accessorKey: 'name',
            header: 'Usuario',
            meta: { label: 'Usuario', primary: true },
            cell: ({ row }) => (
                <div>
                    <div className="flex items-center gap-2 font-medium">
                        {row.original.name}
                        {row.original.es_actual && (
                            <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                Tú
                            </span>
                        )}
                    </div>
                    <div className="text-muted-foreground text-xs">{row.original.email}</div>
                </div>
            ),
        },
        {
            accessorKey: 'role',
            header: 'Rol',
            meta: { label: 'Rol' },
            cell: ({ row }) => (
                <Badge className={roleBadge(row.original.role)}>
                    <ShieldCheck className="mr-1 size-3" />
                    {row.original.role_label}
                </Badge>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) =>
                row.original.is_active ? (
                    <Badge className="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        Activo
                    </Badge>
                ) : (
                    <Badge variant="secondary">Inactivo</Badge>
                ),
        },
        {
            id: 'actions',
            header: '',
            enableSorting: false,
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => {
                const u = row.original;
                return (
                    <DataTableRowActions
                        actions={[
                            {
                                label: 'Editar',
                                icon: Pencil,
                                onSelect: () => router.visit(`/admin/usuarios/${u.id}/editar`),
                            },
                            {
                                label: u.is_active ? 'Desactivar' : 'Activar',
                                icon: Power,
                                disabled: u.es_actual,
                                onSelect: () => toggle(u),
                            },
                            ...(perms.canDelete
                                ? [{
                                      label: 'Eliminar',
                                      icon: Trash2,
                                      danger: true,
                                      separatorBefore: true,
                                      disabled: u.es_actual,
                                      onSelect: () => eliminar(u),
                                  }]
                                : []),
                        ]}
                    />
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuarios" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                        <Users className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Usuarios</h1>
                        <p className="text-muted-foreground text-sm">
                            {usuarios.length} usuario(s) · {usuarios.filter((u) => u.is_active).length} activo(s)
                        </p>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={usuarios}
                    searchPlaceholder="Buscar por nombre o correo..."
                    emptyMessage="Aún no hay usuarios."
                    toolbar={
                        <Button asChild>
                            <Link href="/admin/usuarios/nuevo">
                                <Plus className="size-4" />
                                Nuevo usuario
                            </Link>
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
