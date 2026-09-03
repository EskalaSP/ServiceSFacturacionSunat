import { Head } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { BarChart3 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import { firstDayOfCurrentMonthLima, todayLimaDate } from '@/lib/lima-date';

const hoy = () => todayLimaDate();
const primeroMes = () => firstDayOfCurrentMonthLima();

function esFilaTabla(v: unknown): v is Record<string, unknown>[] {
    return Array.isArray(v) && v.length > 0 && typeof v[0] === 'object' && v[0] !== null;
}

function Valor({ v }: { v: unknown }) {
    if (v === null || v === undefined) return <span className="text-muted-foreground">—</span>;
    if (typeof v === 'number') return <span className="tabular-nums">{v.toLocaleString('es-PE')}</span>;
    if (typeof v === 'object') return <span className="text-muted-foreground">…</span>;
    return <span>{String(v)}</span>;
}

function ReporteTabla({ titulo, filas }: { titulo: string; filas: Record<string, unknown>[] }) {
    const cols = Object.keys(filas[0]);
    const columns: ColumnDef<Record<string, unknown>>[] = cols.map((c) => ({
        id: c,
        header: c,
        accessorFn: (row) => row[c],
        meta: { label: c },
        cell: ({ getValue }) => <Valor v={getValue()} />,
    }));
    return (
        <section className="space-y-2">
            <div className="text-xs font-semibold uppercase text-muted-foreground">{titulo}</div>
            <DataTable
                columns={columns}
                data={filas}
                pageSize={20}
                searchPlaceholder="Buscar en el reporte..."
                emptyMessage="Sin datos en este reporte."
            />
        </section>
    );
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function Reporte({ data }: { data: any }) {
    if (!data || typeof data !== 'object') return null;
    const entradas = Object.entries(data);

    return (
        <div className="space-y-5">
            {entradas.map(([clave, valor]) => {
                if (esFilaTabla(valor)) {
                    return <ReporteTabla key={clave} titulo={clave} filas={valor} />;
                }
                if (typeof valor === 'object' && valor !== null) {
                    return (
                        <section key={clave} className="rounded-xl border border-border bg-card p-4">
                            <div className="mb-2 text-xs font-semibold uppercase text-muted-foreground">{clave}</div>
                            <div className="grid gap-2 sm:grid-cols-3">
                                {Object.entries(valor).map(([k, v]) => (
                                    <div key={k} className="rounded-lg border border-border px-3 py-2">
                                        <div className="text-[10px] uppercase text-muted-foreground">{k}</div>
                                        <div className="text-sm font-medium"><Valor v={v} /></div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    );
                }
                return (
                    <div key={clave} className="rounded-lg border border-border bg-card px-3 py-2">
                        <span className="text-xs uppercase text-muted-foreground">{clave}: </span>
                        <span className="text-sm font-medium"><Valor v={valor} /></span>
                    </div>
                );
            })}
        </div>
    );
}

export default function Reportes() {
    const [desde, setDesde] = useState(primeroMes());
    const [hasta, setHasta] = useState(hoy());
    const [loading, setLoading] = useState(false);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const [data, setData] = useState<any>(null);
    const [error, setError] = useState('');

    const generar = async () => {
        setLoading(true);
        setError('');
        setData(null);
        try {
            const params = new URLSearchParams({ fecha_desde: desde, fecha_hasta: hasta });
            const r = await fetch(`/sunat/reportes/registro-ventas?${params.toString()}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await r.json();
            if (json.ok) setData(json.data);
            else setError(json.error ?? 'No se pudo generar el reporte.');
        } catch (e) {
            setError(String(e));
        } finally {
            setLoading(false);
        }
    };

    return (
        <SunatLayout>
            <Head title="Reportes" />
            <div className="mx-auto max-w-4xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary"><BarChart3 className="size-5" /></span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Registro de ventas</h1>
                        <p className="text-sm text-muted-foreground">Reporte de comprobantes por rango de fechas.</p>
                    </div>
                </header>

                <section className="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-5">
                    <div className="grid gap-1.5"><Label>Desde</Label><Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} /></div>
                    <div className="grid gap-1.5"><Label>Hasta</Label><Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} /></div>
                    <Button type="button" onClick={generar} disabled={loading}>{loading ? 'Generando…' : 'Generar'}</Button>
                </section>

                {error && <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</div>}
                {data && <Reporte data={data} />}
            </div>
        </SunatLayout>
    );
}
