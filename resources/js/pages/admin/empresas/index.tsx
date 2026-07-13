import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Building2, Eye, Hash, Infinity as InfinityIcon, MapPin, Pencil, Plus, Power } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import type { BreadcrumbItem } from '@/types';

type Empresa = {
    id: number;
    ruc: string;
    razon_social: string;
    nombre_comercial: string | null;
    environment: string;
    tax_regime: string | null;
    plan: string;
    emission_mode: string;
    is_active: boolean;
    created_at: string | null;
    sucursales_count: number;
    series_count: number;
};

/**
 * Estado de emisión EFECTIVO, replicando la prioridad de EmissionPolicyService
 * (sin el conteo): global → beta → empresa → plan.
 */
function emisionEfectiva(
    e: Empresa,
    globalIlimitada: boolean,
): { ilimitada: boolean; motivo: string } {
    if (globalIlimitada) return { ilimitada: true, motivo: 'global' };
    if (e.environment === 'beta') return { ilimitada: true, motivo: 'beta' };
    if (e.emission_mode === 'unlimited') return { ilimitada: true, motivo: 'empresa' };
    return { ilimitada: false, motivo: 'plan' };
}

type Paginacion<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    empresas: Paginacion<Empresa>;
    filtros: { buscar: string; plan: string; estado: string };
    emisionGlobalIlimitada: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
];

export default function EmpresasIndex({ empresas, emisionGlobalIlimitada }: Props) {
    const toggle = (t: Empresa) =>
        router.post(`/admin/empresas/${t.id}/toggle`, {}, { preserveScroll: true });

    const columns: ColumnDef<Empresa>[] = [
        {
            accessorKey: 'razon_social',
            header: 'Empresa',
            meta: { label: 'Empresa', primary: true },
            cell: ({ row }) => (
                <div>
                    <div className="font-medium">{row.original.razon_social}</div>
                    <div className="text-muted-foreground font-mono text-xs">{row.original.ruc}</div>
                    {row.original.nombre_comercial && (
                        <div className="text-muted-foreground text-xs">{row.original.nombre_comercial}</div>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'environment',
            header: 'Entorno',
            meta: { label: 'Entorno' },
            cell: ({ row }) => (
                <Badge
                    variant={row.original.environment === 'production' ? 'default' : 'secondary'}
                    className="text-[10px] uppercase"
                >
                    {row.original.environment}
                </Badge>
            ),
        },
        {
            id: 'emision',
            header: 'Emisión',
            enableSorting: false,
            meta: { label: 'Emisión' },
            cell: ({ row }) => {
                const est = emisionEfectiva(row.original, emisionGlobalIlimitada);
                if (est.ilimitada) {
                    return (
                        <div className="flex flex-col gap-0.5">
                            <Badge className="w-fit gap-1 border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                <InfinityIcon className="size-3" />
                                Ilimitada
                            </Badge>
                            <span className="text-muted-foreground text-[10px]">
                                {est.motivo === 'global'
                                    ? 'switch global'
                                    : est.motivo === 'beta'
                                      ? 'entorno beta'
                                      : 'config. empresa'}
                            </span>
                        </div>
                    );
                }
                return (
                    <div className="flex flex-col gap-0.5">
                        <Badge variant="outline" className="w-fit text-[10px] uppercase">
                            Plan: {row.original.plan}
                        </Badge>
                        <span className="text-muted-foreground text-[10px]">por suscripción</span>
                    </div>
                );
            },
        },
        {
            accessorKey: 'sucursales_count',
            header: 'Sucursales',
            meta: { label: 'Sucursales' },
            cell: ({ row }) => (
                <Link
                    href={`/admin/empresas/${row.original.id}/sucursales`}
                    className="text-primary inline-flex items-center gap-1 text-xs hover:underline"
                >
                    <MapPin className="size-3" />
                    {row.original.sucursales_count}
                </Link>
            ),
        },
        {
            accessorKey: 'series_count',
            header: 'Series',
            meta: { label: 'Series' },
            cell: ({ row }) => (
                <Link
                    href={`/admin/empresas/${row.original.id}/series`}
                    className="text-primary inline-flex items-center gap-1 text-xs hover:underline"
                >
                    <Hash className="size-3" />
                    {row.original.series_count}
                </Link>
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
                const t = row.original;
                return (
                    <DataTableRowActions
                        actions={[
                            { label: 'Ver', icon: Eye, onSelect: () => router.visit(`/admin/empresas/${t.id}`) },
                            {
                                label: 'Editar',
                                icon: Pencil,
                                onSelect: () => router.visit(`/admin/empresas/${t.id}/editar`),
                            },
                            {
                                label: 'Sucursales',
                                icon: MapPin,
                                onSelect: () => router.visit(`/admin/empresas/${t.id}/sucursales`),
                            },
                            {
                                label: 'Series',
                                icon: Hash,
                                onSelect: () => router.visit(`/admin/empresas/${t.id}/series`),
                            },
                            {
                                label: t.is_active ? 'Desactivar' : 'Activar',
                                icon: Power,
                                separatorBefore: true,
                                onSelect: () => toggle(t),
                            },
                        ]}
                    />
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Empresas" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                        <Building2 className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Empresas</h1>
                        <p className="text-muted-foreground text-sm">
                            {empresas.total} {empresas.total === 1 ? 'empresa registrada' : 'empresas registradas'}
                        </p>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={empresas.data}
                    searchPlaceholder="Buscar por RUC, razón social..."
                    emptyMessage="No hay empresas registradas."
                    toolbar={
                        <Button asChild>
                            <Link href="/admin/empresas/nueva">
                                <Plus className="size-4" />
                                Nueva empresa
                            </Link>
                        </Button>
                    }
                />

                {/* Paginación server-side (si hay más de una página) */}
                {empresas.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1">
                        {empresas.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    className={`min-w-[36px] rounded-md px-3 py-1.5 text-center text-sm ${
                                        link.active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-muted border'
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
