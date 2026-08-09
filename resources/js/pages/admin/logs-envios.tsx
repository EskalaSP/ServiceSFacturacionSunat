import { Head, router } from '@inertiajs/react';
import { Activity, Clock3, RefreshCw, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pagination, type PaginationLink } from '@/components/ui/pagination';
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

const fecha = (value: string | null) => value ? new Date(value.replace(' ', 'T')).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '—';
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

                <div className="overflow-hidden rounded-xl bg-card shadow-soft">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[980px] text-sm">
                            <thead className="border-b bg-muted/30 text-left text-xs uppercase text-muted-foreground"><tr><th className="p-3">Hora</th><th className="p-3">Empresa</th><th className="p-3">Comprobante</th><th className="p-3">Estado SUNAT</th><th className="p-3">Código / respuesta</th><th className="p-3 text-right">Total</th></tr></thead>
                            <tbody className="divide-y">
                                {logs.data.map((log) => <tr key={`${log.tipo}-${log.id}-${log.tenant_id}`} className="align-top hover:bg-muted/20"><td className="whitespace-nowrap p-3 text-xs text-muted-foreground">{fecha(log.sent_at ?? log.fecha_emision)}</td><td className="max-w-[270px] p-3"><div className="truncate font-medium">{log.razon_social}</div><div className="font-mono text-xs text-muted-foreground">{log.ruc}</div></td><td className="p-3"><div className="font-semibold">{log.tipo}</div><div className="font-mono text-xs">{log.serie}-{String(log.correlativo).padStart(8, '0')}</div></td><td className="p-3"><Estado value={log.estado} /></td><td className="max-w-[420px] p-3"><div className="font-mono text-xs text-muted-foreground">{log.codigo ?? '—'}</div><div className="break-words text-xs">{log.descripcion ?? 'Sin respuesta todavía'}</div></td><td className="p-3 text-right font-mono">{dinero(log.total)}</td></tr>)}
                                {logs.data.length === 0 && <tr><td colSpan={6} className="p-10 text-center text-muted-foreground"><Clock3 className="mx-auto mb-2 size-5" />No hay comprobantes para estos filtros.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination links={logs.links} from={logs.from} to={logs.to} total={logs.total} />
            </div>
        </AppLayout>
    );
}
