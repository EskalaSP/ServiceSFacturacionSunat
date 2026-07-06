import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import type { BreadcrumbItem } from '@/types';

type Serie = {
    id: number;
    tipo_documento: string;
    tipo_nombre: string;
    serie: string;
    correlativo: number;
    sucursal_nombre: string | null;
    is_active: boolean;
};

type Props = {
    tenant: { id: number; ruc: string; razon_social: string };
    series: Serie[];
};

const breadcrumbs = (razon: string, id: number): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: razon, href: `/admin/empresas/${id}` },
    { title: 'Series', href: '#' },
];

export default function SeriesIndex({ tenant, series }: Props) {
    const confirm = useConfirm();

    const toggle = (s: Serie) =>
        router.post(`/admin/empresas/${tenant.id}/series/${s.id}/toggle`, {}, { preserveScroll: true });

    const eliminar = async (s: Serie) => {
        if (
            await confirm({
                title: `¿Eliminar serie ${s.serie}?`,
                description: 'Esto no borra los documentos ya emitidos.',
                variant: 'danger',
                confirmText: 'Eliminar',
            })
        ) {
            router.delete(`/admin/empresas/${tenant.id}/series/${s.id}`, { preserveScroll: true });
        }
    };

    const columns: ColumnDef<Serie>[] = [
        {
            accessorKey: 'serie',
            header: 'Serie',
            meta: { label: 'Serie', primary: true },
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-mono font-semibold">{row.original.serie}</span>
                    <span className="text-muted-foreground text-xs uppercase">
                        {row.original.tipo_nombre} ({row.original.tipo_documento})
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'correlativo',
            header: 'Correlativo',
            meta: { label: 'Correlativo' },
            cell: ({ row }) => (
                <span className="font-mono">{String(row.original.correlativo).padStart(8, '0')}</span>
            ),
        },
        {
            accessorKey: 'sucursal_nombre',
            header: 'Sucursal',
            meta: { label: 'Sucursal' },
            cell: ({ row }) => row.original.sucursal_nombre ?? '—',
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
            cell: ({ row }) => {
                const s = row.original;
                return (
                    <DataTableRowActions
                        actions={[
                            {
                                label: 'Editar',
                                icon: Pencil,
                                onSelect: () =>
                                    router.visit(`/admin/empresas/${tenant.id}/series/${s.id}/editar`),
                            },
                            {
                                label: s.is_active ? 'Desactivar' : 'Activar',
                                icon: Power,
                                onSelect: () => toggle(s),
                            },
                            {
                                label: 'Eliminar',
                                icon: Trash2,
                                danger: true,
                                separatorBefore: true,
                                onSelect: () => eliminar(s),
                            },
                        ]}
                    />
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social, tenant.id)}>
            <Head title={`Series — ${tenant.razon_social}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Series</h1>
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
                    data={series}
                    searchPlaceholder="Buscar serie..."
                    emptyMessage="Aún no hay series. Crea al menos F001 (facturas) y B001 (boletas)."
                    toolbar={
                        <Button asChild>
                            <Link href={`/admin/empresas/${tenant.id}/series/nueva`}>
                                <Plus className="size-4" />
                                Nueva serie
                            </Link>
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
