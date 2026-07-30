import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Download, FileCode2, FileText, FileX, Search, X } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions, type RowAction } from '@/components/ui/data-table-row-actions';
import { Input } from '@/components/ui/input';
import { Pagination, type PaginationLink } from '@/components/ui/pagination';
import type { BreadcrumbItem } from '@/types';

type Comprobante = {
    tipo: string;
    id: number;
    numero: string;
    serie: string | null;
    cliente: string | null;
    cliente_doc: string | null;
    moneda: string | null;
    total: number | string | null;
    estado: string;
    fecha_emision: string | null;
    fecha_envio: string | null;
    has_xml: number;
    has_cdr: number;
    has_pdf: number;
};

type Paginacion<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

type Stats = {
    total: number;
    facturas: number;
    boletas: number;
    notas_credito: number;
    notas_debito: number;
    guias: number;
    enviados: number;
    aceptados: number;
    monto_total: number;
};

type Filtros = Record<string, string | undefined>;

type Props = {
    empresa: { id: number; ruc: string | null; razon_social: string | null };
    comprobantes: Paginacion<Comprobante>;
    stats: Stats;
    filtros: Filtros;
    sucursales: { id: number; nombre: string }[];
    tipos: Record<string, string>;
};

const ESTADO_STYLE: Record<string, string> = {
    aceptado: 'bg-[#00BA5D]/15 text-[#00BA5D]',
    aceptada: 'bg-[#00BA5D]/15 text-[#00BA5D]',
    rechazado: 'bg-[#E63946]/15 text-[#E63946]',
    rechazada: 'bg-[#E63946]/15 text-[#E63946]',
    pendiente: 'bg-[#F0990A]/15 text-[#F0990A]',
    enviado: 'bg-[#3599E6]/15 text-[#3599E6]',
    anulado: 'bg-muted text-muted-foreground',
    anulada: 'bg-muted text-muted-foreground',
    anulacion_en_proceso: 'bg-[#F0990A]/15 text-[#F0990A]',
    vigente: 'bg-[#3599E6]/15 text-[#3599E6]',
    emitida: 'bg-[#3599E6]/15 text-[#3599E6]',
};

const fmtMoneda = (n: number | string | null, moneda: string | null) => {
    if (n === null || n === undefined) return '—';
    const v = typeof n === 'string' ? parseFloat(n) : n;
    const sym = moneda === 'USD' ? '$' : moneda === 'EUR' ? '€' : 'S/';
    return `${sym} ${v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const fmtFecha = (iso: string | null) => (iso ? String(iso).slice(0, 10).split('-').reverse().join('/') : '—');

function EstadoBadge({ estado }: { estado: string }) {
    return (
        <span className={`inline-flex rounded-md px-2 py-0.5 text-xs font-bold capitalize ${ESTADO_STYLE[estado] ?? 'bg-muted text-muted-foreground'}`}>
            {estado?.replace(/_/g, ' ')}
        </span>
    );
}

export default function EmpresaComprobantes({ empresa, comprobantes, stats, filtros, sucursales, tipos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Administración', href: '#' },
        { title: 'Empresas', href: '/admin/empresas' },
        { title: 'Comprobantes', href: '#' },
    ];

    const [f, setF] = useState<Filtros>(filtros ?? {});
    const base = `/admin/empresas/${empresa.id}/comprobantes`;
    const dl = (c: Comprobante, formato: string) => `${base}/${c.tipo}/${c.id}/${formato}`;

    const aplicar = (extra: Filtros = {}) => {
        const params: Filtros = { ...f, ...extra };
        Object.keys(params).forEach((k) => (!params[k] ? delete params[k] : null));
        router.get(base, params, { preserveScroll: true, preserveState: true, replace: true });
    };

    const limpiar = () => {
        setF({});
        router.get(base, {}, { preserveScroll: true, replace: true });
    };

    const columns: ColumnDef<Comprobante>[] = [
        {
            accessorKey: 'tipo',
            header: 'Tipo',
            meta: { label: 'Tipo' },
            cell: ({ row }) => (
                <Badge variant="secondary" className="font-semibold whitespace-nowrap">
                    {tipos[row.original.tipo] ?? row.original.tipo}
                </Badge>
            ),
        },
        {
            accessorKey: 'numero',
            header: 'Número',
            meta: { primary: true },
            cell: ({ row }) => <span className="font-bold tabular-nums">{row.original.numero}</span>,
        },
        {
            accessorKey: 'cliente',
            header: 'Cliente',
            meta: { label: 'Cliente' },
            cell: ({ row }) => (
                <div className="max-w-[240px]">
                    <div className="truncate font-medium">{row.original.cliente ?? '—'}</div>
                    {row.original.cliente_doc && <div className="text-xs text-muted-foreground">{row.original.cliente_doc}</div>}
                </div>
            ),
        },
        {
            accessorKey: 'total',
            header: 'Total',
            meta: { label: 'Total', alignRight: true },
            cell: ({ row }) => <span className="font-semibold tabular-nums">{fmtMoneda(row.original.total, row.original.moneda)}</span>,
        },
        {
            accessorKey: 'estado',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) => <EstadoBadge estado={row.original.estado} />,
        },
        {
            accessorKey: 'fecha_emision',
            header: 'Emisión',
            meta: { label: 'Emisión' },
            cell: ({ row }) => <span className="tabular-nums">{fmtFecha(row.original.fecha_emision)}</span>,
        },
        {
            accessorKey: 'fecha_envio',
            header: 'Envío',
            meta: { label: 'Envío' },
            cell: ({ row }) => <span className="tabular-nums text-muted-foreground">{fmtFecha(row.original.fecha_envio)}</span>,
        },
        {
            id: 'actions',
            header: '',
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => {
                const c = row.original;
                const acts: RowAction[] = [];
                if (c.has_pdf) acts.push({ label: 'Ver / PDF', icon: FileText, iconClassName: 'text-[#E63946]', onSelect: () => window.open(dl(c, 'pdf'), '_blank') });
                if (c.has_xml) acts.push({ label: 'Descargar XML', icon: FileCode2, iconClassName: 'text-[#3599E6]', onSelect: () => { window.location.href = dl(c, 'xml'); } });
                if (c.has_cdr) acts.push({ label: 'Descargar CDR', icon: Download, iconClassName: 'text-[#00BA5D]', onSelect: () => { window.location.href = dl(c, 'cdr'); } });
                if (acts.length === 0) acts.push({ label: 'Sin archivos', icon: FileX, onSelect: () => {}, disabled: true });
                return <DataTableRowActions actions={acts} />;
            },
        },
    ];

    const cards = [
        { label: 'Total', value: stats.total, accent: 'text-foreground' },
        { label: 'Facturas', value: stats.facturas, accent: 'text-[#3599E6]' },
        { label: 'Boletas', value: stats.boletas, accent: 'text-[#8B5CF6]' },
        { label: 'N. Crédito', value: stats.notas_credito, accent: 'text-[#F0990A]' },
        { label: 'N. Débito', value: stats.notas_debito, accent: 'text-[#E63946]' },
        { label: 'Guías', value: stats.guias, accent: 'text-[#00BA5D]' },
        { label: 'Aceptados', value: stats.aceptados, accent: 'text-[#00BA5D]' },
        { label: 'Monto total', value: fmtMoneda(stats.monto_total, 'PEN'), accent: 'text-foreground' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Comprobantes · ${empresa.razon_social ?? empresa.ruc}`} />

            <div className="flex flex-1 flex-col gap-5 p-4">
                {/* Encabezado */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Comprobantes de la empresa</h1>
                    <p className="text-sm text-muted-foreground">
                        {empresa.razon_social} · RUC {empresa.ruc}
                    </p>
                </div>

                {/* Tarjetas resumen */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {cards.map((c) => (
                        <div key={c.label} className="rounded-xl bg-card p-4 shadow-soft">
                            <div className="text-xs font-semibold text-muted-foreground">{c.label}</div>
                            <div className={`mt-1 truncate text-xl font-bold ${c.accent}`}>{c.value}</div>
                        </div>
                    ))}
                </div>

                {/* Filtros */}
                <div className="rounded-xl bg-card p-4 shadow-soft">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="relative sm:col-span-2 lg:col-span-2">
                            <Search className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input
                                className="pl-8"
                                placeholder="Buscar número, cliente, RUC/DNI…"
                                value={f.buscar ?? ''}
                                onChange={(e) => setF({ ...f, buscar: e.target.value })}
                                onKeyDown={(e) => e.key === 'Enter' && aplicar()}
                            />
                        </div>
                        <Combobox
                            placeholder="Todos los tipos"
                            value={f.tipo ?? ''}
                            onChange={(v) => aplicar({ tipo: v })}
                            options={[{ value: '', label: 'Todos los tipos' }, ...Object.entries(tipos).map(([k, v]) => ({ value: k, label: v }))]}
                        />
                        <Combobox
                            placeholder="Todos los estados"
                            value={f.estado ?? ''}
                            onChange={(v) => aplicar({ estado: v })}
                            options={[
                                { value: '', label: 'Todos los estados' },
                                { value: 'aceptado', label: 'Aceptado' },
                                { value: 'rechazado', label: 'Rechazado' },
                                { value: 'pendiente', label: 'Pendiente' },
                                { value: 'enviado', label: 'Enviado' },
                                { value: 'anulado', label: 'Anulado' },
                            ]}
                        />
                        <Combobox
                            value={`${f.sort ?? 'fecha_emision'}:${f.dir ?? 'desc'}`}
                            onChange={(v) => { const [sort, dir] = v.split(':'); aplicar({ sort, dir }); }}
                            options={[
                                { value: 'fecha_emision:desc', label: 'Más reciente' },
                                { value: 'fecha_emision:asc', label: 'Más antiguo' },
                                { value: 'total:desc', label: 'Mayor monto' },
                                { value: 'total:asc', label: 'Menor monto' },
                                { value: 'tipo:asc', label: 'Tipo' },
                                { value: 'estado:asc', label: 'Estado' },
                            ]}
                        />
                        {sucursales.length > 0 && (
                            <Combobox
                                searchable
                                placeholder="Todas las sucursales"
                                value={f.sucursal_id ?? ''}
                                onChange={(v) => aplicar({ sucursal_id: v })}
                                options={[{ value: '', label: 'Todas las sucursales' }, ...sucursales.map((s) => ({ value: String(s.id), label: s.nombre }))]}
                            />
                        )}
                        <Input placeholder="Serie" value={f.serie ?? ''} onChange={(e) => setF({ ...f, serie: e.target.value })} onKeyDown={(e) => e.key === 'Enter' && aplicar()} />
                        <div className="flex items-center gap-2">
                            <label className="text-xs font-medium text-muted-foreground">Desde</label>
                            <Input type="date" value={f.fecha_desde ?? ''} onChange={(e) => aplicar({ fecha_desde: e.target.value })} />
                        </div>
                        <div className="flex items-center gap-2">
                            <label className="text-xs font-medium text-muted-foreground">Hasta</label>
                            <Input type="date" value={f.fecha_hasta ?? ''} onChange={(e) => aplicar({ fecha_hasta: e.target.value })} />
                        </div>
                        <div className="flex items-center gap-2">
                            <Button onClick={() => aplicar()} className="flex-1">Aplicar</Button>
                            <Button variant="outline" onClick={limpiar} title="Limpiar filtros"><X className="size-4" /></Button>
                        </div>
                    </div>
                </div>

                {/* Tabla responsiva (tabla en escritorio · cards en móvil) */}
                <DataTable
                    columns={columns}
                    data={comprobantes.data}
                    searchable={false}
                    manualPagination
                    emptyMessage="No se encontraron comprobantes con esos filtros."
                />

                {/* Paginación circular */}
                <Pagination
                    links={comprobantes.links}
                    from={comprobantes.from}
                    to={comprobantes.to}
                    total={comprobantes.total}
                    left={
                        <Combobox
                            className="h-8 w-28"
                            value={f.per_page ?? '25'}
                            onChange={(v) => aplicar({ per_page: v })}
                            options={[
                                { value: '25', label: '25 / pág.' },
                                { value: '50', label: '50 / pág.' },
                                { value: '100', label: '100 / pág.' },
                            ]}
                        />
                    }
                />
            </div>
        </AppLayout>
    );
}
