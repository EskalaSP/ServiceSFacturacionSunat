import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { CreditCard, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import type { BreadcrumbItem } from '@/types';

type Plan = {
    id: number;
    slug: string;
    name: string;
    price_monthly: number;
    price_yearly: number | null;
    documents_month: number;
    features_count: number;
    sort_order: number;
    is_active: boolean;
    subscriptions_count: number;
};

type Props = { planes: Plan[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Planes', href: '/admin/planes' },
];

const fmt = (n: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);

export default function PlanesIndex({ planes }: Props) {
    const confirm = useConfirm();

    const toggle = (p: Plan) =>
        router.post(`/admin/planes/${p.id}/toggle`, {}, { preserveScroll: true });

    const eliminar = async (p: Plan) => {
        if (p.subscriptions_count > 0) {
            await confirm({
                title: 'No se puede eliminar',
                description: `"${p.name}" tiene ${p.subscriptions_count} empresa(s) asociada(s). Desactívalo en su lugar.`,
                confirmText: 'Entendido',
                cancelText: 'Cerrar',
            });
            return;
        }
        if (
            await confirm({
                title: `¿Eliminar plan "${p.name}"?`,
                description: 'Esta acción no se puede deshacer.',
                variant: 'danger',
                confirmText: 'Eliminar',
            })
        ) {
            router.delete(`/admin/planes/${p.id}`, { preserveScroll: true });
        }
    };

    const columns: ColumnDef<Plan>[] = [
        {
            accessorKey: 'name',
            header: 'Nombre',
            meta: { label: 'Nombre', primary: true },
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.name}</div>
                    <div className="text-muted-foreground font-mono text-xs">{row.original.slug}</div>
                </div>
            ),
        },
        {
            accessorKey: 'price_monthly',
            header: 'Mensual',
            meta: { label: 'Mensual' },
            cell: ({ row }) => fmt(row.original.price_monthly),
        },
        {
            accessorKey: 'price_yearly',
            header: 'Anual',
            meta: { label: 'Anual' },
            cell: ({ row }) => (row.original.price_yearly ? fmt(row.original.price_yearly) : '—'),
        },
        {
            accessorKey: 'documents_month',
            header: 'Docs/mes',
            meta: { label: 'Docs/mes' },
            cell: ({ row }) => (
                <span className="font-mono">
                    {row.original.documents_month === -1 ? '∞' : row.original.documents_month}
                </span>
            ),
        },
        {
            accessorKey: 'subscriptions_count',
            header: 'Empresas',
            meta: { label: 'Empresas' },
            cell: ({ row }) =>
                row.original.subscriptions_count > 0 ? (
                    <span className="font-medium">{row.original.subscriptions_count}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
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
                const p = row.original;
                return (
                    <DataTableRowActions
                        actions={[
                            {
                                label: 'Editar',
                                icon: Pencil,
                                onSelect: () => router.visit(`/admin/planes/${p.id}/editar`),
                            },
                            {
                                label: p.is_active ? 'Desactivar' : 'Activar',
                                icon: Power,
                                onSelect: () => toggle(p),
                            },
                            {
                                label: 'Eliminar',
                                icon: Trash2,
                                danger: true,
                                separatorBefore: true,
                                onSelect: () => eliminar(p),
                            },
                        ]}
                    />
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planes" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                        <CreditCard className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Planes</h1>
                        <p className="text-muted-foreground text-sm">
                            {planes.length} planes · {planes.filter((p) => p.is_active).length} activos
                        </p>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={planes}
                    searchPlaceholder="Buscar plan..."
                    emptyMessage="Aún no hay planes definidos."
                    toolbar={
                        <Button asChild>
                            <Link href="/admin/planes/nuevo">
                                <Plus className="size-4" />
                                Nuevo plan
                            </Link>
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
