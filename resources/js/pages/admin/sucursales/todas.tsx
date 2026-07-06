import { Head, Link, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { MapPin, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import type { BreadcrumbItem } from '@/types';

type Sucursal = {
    id: number;
    nombre: string;
    cod_local: string;
    direccion: string | null;
    is_principal: boolean;
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
    sucursales: Paginacion<Sucursal>;
    empresas: Empresa[];
    filtros: { buscar: string; tenant_id: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Sucursales', href: '/admin/sucursales' },
];

export default function SucursalesTodas({ sucursales, empresas, filtros }: Props) {
    const confirm = useConfirm();
    const { data, setData, get, processing } = useForm({
        buscar: filtros.buscar || '',
        tenant_id: filtros.tenant_id || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        get('/admin/sucursales', { preserveState: true, preserveScroll: true });
    };

    const eliminar = async (s: Sucursal) => {
        if (
            await confirm({
                title: `¿Eliminar sucursal "${s.nombre}"?`,
                description: `${s.tenant.razon_social} · esta acción no se puede deshacer.`,
                variant: 'danger',
                confirmText: 'Eliminar',
            })
        ) {
            router.delete(`/admin/empresas/${s.tenant.id}/sucursales/${s.id}`, { preserveScroll: true });
        }
    };

    const columns: ColumnDef<Sucursal>[] = [
        {
            accessorKey: 'nombre',
            header: 'Sucursal',
            meta: { label: 'Sucursal', primary: true },
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium">{row.original.nombre}</span>
                    {row.original.is_principal && <Badge className="text-[10px]">Principal</Badge>}
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
            accessorKey: 'cod_local',
            header: 'Cód. Local',
            meta: { label: 'Cód. Local' },
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.cod_local}</span>,
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
                                router.visit(
                                    `/admin/empresas/${row.original.tenant.id}/sucursales/${row.original.id}/editar`,
                                ),
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sucursales" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                        <MapPin className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Sucursales</h1>
                        <p className="text-muted-foreground text-sm">
                            {sucursales.total} {sucursales.total === 1 ? 'sucursal' : 'sucursales'} en total
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
                        <Button type="submit" variant="secondary" disabled={processing}>
                            Filtrar por empresa
                        </Button>
                        {data.tenant_id && (
                            <Button asChild className="sm:ml-auto">
                                <Link href={`/admin/empresas/${data.tenant_id}/sucursales/nueva`}>Nueva sucursal</Link>
                            </Button>
                        )}
                    </form>
                </Card>

                <DataTable
                    columns={columns}
                    data={sucursales.data}
                    searchPlaceholder="Buscar por nombre, cód local..."
                    emptyMessage="No hay sucursales que coincidan."
                />

                {sucursales.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1">
                        {sucursales.links.map((link, i) =>
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
