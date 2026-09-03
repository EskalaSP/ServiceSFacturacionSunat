import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Activity, RefreshCw, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Pagination, type PaginationLink } from '@/components/ui/pagination';
import { formatDateTimeLima } from '@/lib/lima-date';
import type { BreadcrumbItem } from '@/types';

type Log = {
    tipo: string;
    id: number;
    tenant_id: number;
    serie: string;
    correlativo: string | number;
    estado: string;
    codigo: string | null;
    descripcion: string | null;
    fecha_emision: string | null;
    sent_at: string | null;
    total: number | string | null;
    ruc: string;
    razon_social: string;
};

type Props = {
    logs: { data: Log[]; from: number | null; to: number | null; total: number; links: PaginationLink[] };
    filtros: { estado?: string; buscar?: string };
};

const estadoStyle: Record<string, string> = {
    aceptado: 'bg-emerald-500/15 text-emerald-600',
    rechazado: 'bg-red-500/15 text-red-600',
    enviado: 'bg-sky-500/15 text-sky-600',
    pendiente: 'bg-amber-500/15 text-amber-600',
};

const fecha = (value: string | null) => value ? formatDateTimeLima(value) : '—';
const dinero = (value: number | string | null) => value === null ? '—' : `S/ ${Number(value).toLocaleString('es-PE', { minimumFractionDigits: 2 })}`;

function Estado({ value }: { value: string }) {
    return <Badge className={`capitalize ${estadoStyle[value] ?? 'bg-muted text-muted-foreground'}`} variant="secondary">{value}</Badge>;
}

export default function LogsEnvios({ logs, filtros }: Props) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const base = '/admin/logs-envios';
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Inicio', href: '/dashboard' },
        { title: 'Logs de envíos', href: base },
    ];

    useEffect(() => {
        const timer = window.setInterval(() => router.reload({ only: ['logs'], preserveScroll: true, preserveState: true }), 10000);
        return () => window.clearInterval(timer);
    }, []);

    const filtrar = (estado = filtros.estado ?? '') => {
        router.get(base, { estado, buscar: buscar || undefined }, { preserveScroll: true, replace: true });
    };

    const columns: ColumnDef<Log>[] = [
        {
            accessorKey: 'sent_at',
            header: 'Hora',
            meta: { label: 'Hora' },
            cell: ({ row }) => <span className="whitespace-nowrap text-xs text-muted-foreground">{fecha(row.original.sent_at ?? row.original.fecha_emision)}</span>,
        },
        {
            accessorKey: 'razon_social',
            header: 'Empresa',
            meta: { label: 'Empresa', primary: true },
            cell: ({ row }) => (
                <div className="max-w-[270px]">
                    <div className="truncate font-medium">{row.original.razon_social}</div>
                    <div className="font-mono text-xs text-muted-foreground">{row.original.ruc}</div>
                </div>
            ),
        },
        {
            id: 'comprobante',
            header: 'Comprobante',
            enableSorting: false,
            meta: { label: 'Comprobante' },
            cell: ({ row }) => (
                <div>
                    <div className="font-semibold">{row.original.tipo}</div>
                    <div className="font-mono text-xs">{row.original.serie}-{String(row.original.correlativo).padStart(8, '0')}</div>
                </div>
            ),
        },
        {
            accessorKey: 'estado',
            header: 'Estado SUNAT',
            meta: { label: 'Estado' },
            cell: ({ row }) => <Estado value={row.original.estado} />,
        },
        {
            id: 'respuesta',
            header: 'Código / respuesta',
            enableSorting: false,
            meta: { label: 'Código / respuesta' },
            cell: ({ row }) => (
                <div className="max-w-[420px]">
                    <div className="font-mono text-xs text-muted-foreground">{row.original.codigo ?? '—'}</div>
                    <div className="break-words text-xs">{row.original.descripcion ?? 'Sin respuesta todavía'}</div>
                </div>
            ),
        },
        {
            accessorKey: 'total',
            header: 'Total',
            meta: { label: 'Total', alignRight: true },
            cell: ({ row }) => <span className="font-mono">{dinero(row.original.total)}</span>,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Logs de envíos" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2"><Activity className="size-5 text-sky-500" /><h1 className="text-2xl font-semibold tracking-tight">Logs de envíos</h1></div>
                        <p className="text-sm text-muted-foreground">Últimos comprobantes de todas las empresas · actualización automática cada 10 s</p>
                    </div>
                    <Button variant="outline" onClick={() => router.reload({ only: ['logs'], preserveScroll: true })}><RefreshCw className="mr-2 size-4" />Actualizar</Button>
                </div>

                <div className="flex flex-wrap gap-2 rounded-xl bg-card p-4 shadow-soft">
                    <div className="relative min-w-[260px] flex-1"><Search className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" /><Input className="pl-8" value={buscar} placeholder="Buscar RUC, empresa, serie o error" onChange={(e) => setBuscar(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && filtrar()} /></div>
                    {['', 'aceptado', 'rechazado', 'enviado', 'pendiente'].map((estado) => <Button key={estado} variant={(filtros.estado ?? '') === estado ? 'default' : 'outline'} onClick={() => filtrar(estado)}>{estado || 'Todos'}</Button>)}
                    <Button variant="ghost" onClick={() => { setBuscar(''); router.get(base); }}><X className="size-4" /></Button>
                </div>

                <DataTable
                    columns={columns}
                    data={logs.data}
                    searchable={false}
                    manualPagination
                    emptyMessage="No hay comprobantes para estos filtros."
                />

                <Pagination links={logs.links} from={logs.from} to={logs.to} total={logs.total} />
            </div>
        </AppLayout>
    );
}
