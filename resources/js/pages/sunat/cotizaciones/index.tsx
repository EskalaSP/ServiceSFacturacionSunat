import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FileText, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SunatLayout from '@/layouts/sunat-layout';

type Cotizacion = {
    id: number;
    numero: string;
    fecha_emision: string;
    fecha_vencimiento: string | null;
    cliente: string;
    total: number;
    moneda: string;
    status: 'vigente' | 'aceptada' | 'rechazada' | 'vencida';
    observacion: string | null;
};

type PaginatedCotizaciones = {
    data: Cotizacion[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    cotizaciones: PaginatedCotizaciones;
    filtros: { q: string; status: string };
    tenant: { ruc: string; razon_social: string; environment: string };
};

const STATUS_CONFIG: Record<string, { label: string; className: string }> = {
    vigente:   { label: 'Vigente',   className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
    aceptada:  { label: 'Aceptada',  className: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' },
    rechazada: { label: 'Rechazada', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
    vencida:   { label: 'Vencida',   className: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' },
};

function fmt(n: number) {
    return new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2 }).format(n);
}

function fmtDate(iso: string | null) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

export default function CotizacionesIndex({ cotizaciones, filtros, tenant }: Props) {
    const [q, setQ]             = useState(filtros.q);
    const [status, setStatus]   = useState(filtros.status);
    const [confirmarId, setConfirmarId] = useState<number | null>(null);

    function buscar() {
        router.get('/sunat/cotizaciones', { q, status }, { preserveState: true });
    }

    function cambiarEstado(id: number, nuevoEstado: string) {
        router.patch(`/sunat/cotizaciones/${id}/estado`, { status: nuevoEstado }, { preserveState: true });
    }

    function convertir(id: number) {
        router.post(`/sunat/cotizaciones/${id}/convertir`);
    }

    function eliminar(id: number) {
        router.delete(`/sunat/cotizaciones/${id}`, { onFinish: () => setConfirmarId(null) });
    }

    return (
        <SunatLayout>
            <Head title="Cotizaciones" />

            <div className="mx-auto max-w-6xl">

                {/* Header */}
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Cotizaciones</h1>
                        <p className="text-sm text-muted-foreground">{cotizaciones.total} cotización{cotizaciones.total !== 1 ? 'es' : ''} registrada{cotizaciones.total !== 1 ? 's' : ''}</p>
                    </div>
                    <Button onClick={() => router.visit('/sunat/cotizaciones/nueva')} className="gap-2 rounded-xl">
                        <Plus className="size-4" /> Nueva Cotización
                    </Button>
                </div>

                {/* Filtros */}
                <div className="mb-5 flex flex-wrap gap-3">
                    <Input
                        placeholder="Buscar por número o cliente..."
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && buscar()}
                        className="h-9 w-64 rounded-xl"
                    />
                    <select
                        value={status}
                        onChange={(e) => { setStatus(e.target.value); router.get('/sunat/cotizaciones', { q, status: e.target.value }, { preserveState: true }); }}
                        className="h-9 rounded-xl border border-border bg-background px-3 text-sm outline-none"
                    >
                        <option value="todos">Todos los estados</option>
                        <option value="vigente">Vigente</option>
                        <option value="aceptada">Aceptada</option>
                        <option value="rechazada">Rechazada</option>
                        <option value="vencida">Vencida</option>
                    </select>
                    <Button variant="outline" size="sm" onClick={buscar} className="h-9 rounded-xl">
                        Buscar
                    </Button>
                </div>

                {/* Tabla */}
                <div className="rounded-2xl border border-border bg-card shadow-soft overflow-hidden">
                    {cotizaciones.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <FileText className="mb-3 size-10 text-muted-foreground/40" />
                            <p className="text-sm font-medium text-muted-foreground">No hay cotizaciones</p>
                            <p className="mt-1 text-xs text-muted-foreground">Crea tu primera cotización para enviar a un cliente.</p>
                            <Button onClick={() => router.visit('/sunat/cotizaciones/nueva')} className="mt-4 gap-2 rounded-xl" size="sm">
                                <Plus className="size-3.5" /> Nueva Cotización
                            </Button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border/60 bg-muted/30">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Número</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Cliente</th>
                                        <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Fecha</th>
                                        <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Vence</th>
                                        <th className="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Total</th>
                                        <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Estado</th>
                                        <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cotizaciones.data.map((cot) => {
                                        const st = STATUS_CONFIG[cot.status] ?? STATUS_CONFIG.vigente;
                                        return (
                                            <tr key={cot.id} className="border-b border-border/40 last:border-0 hover:bg-muted/20 transition-colors">
                                                <td className="px-4 py-3">
                                                    <span className="font-mono text-xs font-medium">{cot.numero}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="font-medium">{cot.cliente}</span>
                                                    {cot.observacion && (
                                                        <p className="mt-0.5 text-[11px] text-muted-foreground line-clamp-1">{cot.observacion}</p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center text-xs text-muted-foreground">{fmtDate(cot.fecha_emision)}</td>
                                                <td className="px-4 py-3 text-center">
                                                    {cot.fecha_vencimiento
                                                        ? <span className="text-xs text-amber-600 font-medium">{fmtDate(cot.fecha_vencimiento)}</span>
                                                        : <span className="text-xs text-muted-foreground">—</span>
                                                    }
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums font-semibold">
                                                    {cot.moneda === 'USD' ? '$' : 'S/'} {fmt(cot.total)}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <select
                                                        value={cot.status}
                                                        onChange={(e) => cambiarEstado(cot.id, e.target.value)}
                                                        className={`rounded-full border-0 px-2.5 py-1 text-[11px] font-medium outline-none cursor-pointer ${st.className}`}
                                                    >
                                                        <option value="vigente">Vigente</option>
                                                        <option value="aceptada">Aceptada</option>
                                                        <option value="rechazada">Rechazada</option>
                                                        <option value="vencida">Vencida</option>
                                                    </select>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        {(cot.status === 'vigente' || cot.status === 'aceptada') && (
                                                            <button
                                                                type="button"
                                                                onClick={() => convertir(cot.id)}
                                                                title="Convertir en Factura"
                                                                className="inline-flex items-center gap-1 rounded-lg border border-primary/30 bg-primary/5 px-2.5 py-1 text-[11px] font-medium text-primary transition-colors hover:bg-primary/10"
                                                            >
                                                                <RefreshCw className="size-3" /> Facturar
                                                            </button>
                                                        )}
                                                        {confirmarId === cot.id ? (
                                                            <div className="flex items-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => eliminar(cot.id)}
                                                                    className="rounded-lg bg-destructive px-2 py-1 text-[11px] font-medium text-destructive-foreground"
                                                                >
                                                                    Confirmar
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setConfirmarId(null)}
                                                                    className="rounded-lg border px-2 py-1 text-[11px] text-muted-foreground"
                                                                >
                                                                    No
                                                                </button>
                                                            </div>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                onClick={() => setConfirmarId(cot.id)}
                                                                title="Eliminar"
                                                                className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:text-destructive"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Paginación */}
                {cotizaciones.last_page > 1 && (
                    <div className="mt-4 flex justify-center gap-1">
                        {cotizaciones.links.map((link, i) => (
                            <button
                                key={i}
                                type="button"
                                disabled={!link.url || link.active}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`rounded-lg px-3 py-1.5 text-xs transition-colors ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground font-medium'
                                        : link.url
                                            ? 'border border-border hover:bg-secondary'
                                            : 'text-muted-foreground cursor-not-allowed'
                                }`}
                            />
                        ))}
                    </div>
                )}
            </div>
        </SunatLayout>
    );
}
