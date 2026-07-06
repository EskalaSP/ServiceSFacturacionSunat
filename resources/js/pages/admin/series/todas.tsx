import { Head, Link, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Hash, Pencil, Power, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
    tenant: { id: number; ruc: string; razon_social: string };
};

type Empresa = { id: number; ruc: string; razon_social: string };

type Paginacion<T> = {
    data: T[];
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    series: Paginacion<Serie>;
    empresas: Empresa[];
    tipos: Record<string, string>;
    filtros: { buscar: string; tenant_id: string; tipo_documento: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Series', href: '/admin/series' },
];

export default function SeriesTodas({ series, empresas, tipos, filtros }: Props) {
    const confirm = useConfirm();
    const { data, setData, get, processing } = useForm({
        buscar: filtros.buscar || '',
        tenant_id: filtros.tenant_id || '',
        tipo_documento: filtros.tipo_documento || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        get('/admin/series', { preserveState: true, preserveScroll: true });
    };

    const toggle = (s: Serie) =>
        router.post(`/admin/empresas/${s.tenant.id}/series/${s.id}/toggle`, {}, { preserveScroll: true });

    const eliminar = async (s: Serie) => {
        if (
            await confirm({
                title: `¿Eliminar serie ${s.serie}?`,
                description: `${s.tenant.razon_social} · esto no borra los documentos ya emitidos.`,
                variant: 'danger',
                confirmText: 'Eliminar',
            })
        ) {
            router.delete(`/admin/empresas/${s.tenant.id}/series/${s.id}`, { preserveScroll: true });
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
            id: 'empresa',
            header: 'Empresa',
            meta: { label: 'Empresa' },
            cell: ({ row }) => (
                <Link href={`/admin/empresas/${row.original.tenant.id}`} className="text-primary hover:underline">
                    <div className="font-medium">{row.original.tenant.razon_social}</div>
                    <div className="text-muted-foreground font-mono text-xs">{row.original.tenant.ruc}</div>
                </Link>
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
                                    router.visit(`/admin/empresas/${s.tenant.id}/series/${s.id}/editar`),
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Series" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                        <Hash className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Series</h1>
                        <p className="text-muted-foreground text-sm">
                            {series.total} {series.total === 1 ? 'serie' : 'series'} en total
                        </p>
                    </div>
                </div>

                <Card className="p-4">
                    <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <select
                            className="form-select sm:max-w-xs"
                            value={data.tenant_id}
                            onChange={(e) => setData('tenant_id', e.target.value)}
                        >
                            <option value="">Todas las empresas</option>
                            {empresas.map((emp) => (
                                <option key={emp.id} value={emp.id}>
                                    {emp.ruc} — {emp.razon_social}
                                </option>
                            ))}
                        </select>
                        <select
                            className="form-select sm:max-w-[200px]"
                            value={data.tipo_documento}
                            onChange={(e) => setData('tipo_documento', e.target.value)}
                        >
                            <option value="">Todos los tipos</option>
                            {Object.entries(tipos).map(([codigo, nombre]) => (
                                <option key={codigo} value={codigo}>
                                    {codigo} — {nombre}
                                </option>
                            ))}
                        </select>
                        <Button type="submit" variant="secondary" disabled={processing}>
                            Filtrar
                        </Button>
                        {data.tenant_id && (
                            <Button asChild className="sm:ml-auto">
                                <Link href={`/admin/empresas/${data.tenant_id}/series/nueva`}>Nueva serie</Link>
                            </Button>
                        )}
                    </form>
                </Card>

                <DataTable
                    columns={columns}
                    data={series.data}
                    searchPlaceholder="Buscar por serie..."
                    emptyMessage="No hay series que coincidan."
                />

                {series.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1">
                        {series.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    className={`min-w-[36px] rounded-md px-3 py-1.5 text-center text-sm ${
                                        link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted border'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="min-w-[36px] rounded-md px-3 py-1.5 text-center text-sm opacity-40"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
