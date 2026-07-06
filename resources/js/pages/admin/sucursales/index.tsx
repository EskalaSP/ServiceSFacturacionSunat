import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import type { BreadcrumbItem } from '@/types';

type Sucursal = {
    id: number;
    nombre: string;
    cod_local: string;
    direccion: string | null;
    ubigeo: string | null;
    telefono: string | null;
    email: string | null;
    is_principal: boolean;
    is_active: boolean;
};

type Props = {
    tenant: { id: number; ruc: string; razon_social: string };
    sucursales: Sucursal[];
};

const breadcrumbs = (razon: string, id: number): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: razon, href: `/admin/empresas/${id}` },
    { title: 'Sucursales', href: '#' },
];

export default function SucursalesIndex({ tenant, sucursales }: Props) {
    const confirm = useConfirm();

    const eliminar = async (s: Sucursal) => {
        if (
            await confirm({
                title: `¿Eliminar sucursal "${s.nombre}"?`,
                description: 'Esta acción no se puede deshacer.',
                variant: 'danger',
                confirmText: 'Eliminar',
            })
        ) {
            router.delete(`/admin/empresas/${tenant.id}/sucursales/${s.id}`, { preserveScroll: true });
        }
    };

    const columns: ColumnDef<Sucursal>[] = [
        {
            accessorKey: 'nombre',
            header: 'Nombre',
            meta: { label: 'Nombre', primary: true },
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium">{row.original.nombre}</span>
                    {row.original.is_principal && <Badge className="text-[10px]">Principal</Badge>}
                </div>
            ),
        },
        {
            accessorKey: 'cod_local',
            header: 'Cód. Local',
            meta: { label: 'Cód. Local' },
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.cod_local}</span>,
        },
        {
            accessorKey: 'direccion',
            header: 'Dirección',
            meta: { label: 'Dirección' },
            cell: ({ row }) => (
                <span className="text-muted-foreground">{row.original.direccion ?? '—'}</span>
            ),
        },
        {
            accessorKey: 'ubigeo',
            header: 'Ubigeo',
            meta: { label: 'Ubigeo' },
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.ubigeo ?? '—'}</span>,
        },
        {
            accessorKey: 'is_active',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) =>
                row.original.is_active ? (
                    <Badge className="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        Activa
                    </Badge>
                ) : (
                    <Badge variant="secondary">Inactiva</Badge>
                ),
        },
        {
            id: 'actions',
            header: '',
            enableSorting: false,
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => (
                <DataTableRowActions
                    actions={[
                        {
                            label: 'Editar',
                            icon: Pencil,
                            onSelect: () =>
                                router.visit(`/admin/empresas/${tenant.id}/sucursales/${row.original.id}/editar`),
                        },
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            danger: true,
                            separatorBefore: true,
                            onSelect: () => eliminar(row.original),
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social, tenant.id)}>
            <Head title={`Sucursales — ${tenant.razon_social}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Sucursales</h1>
                        <p className="text-muted-foreground text-sm">{tenant.razon_social}</p>
                    </div>
                    <Button variant="ghost" asChild>
                        <Link href={`/admin/empresas/${tenant.id}`}>
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                <DataTable
                    columns={columns}
                    data={sucursales}
                    searchPlaceholder="Buscar sucursal..."
                    emptyMessage="Aún no hay sucursales."
                    toolbar={
                        <Button asChild>
                            <Link href={`/admin/empresas/${tenant.id}/sucursales/nueva`}>
                                <Plus className="size-4" />
                                Nueva sucursal
                            </Link>
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
