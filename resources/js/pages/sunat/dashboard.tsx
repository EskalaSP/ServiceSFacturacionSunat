import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowDownRight, ArrowUpRight, CheckCircle2, Clock, FileText, Plus, Users, Wallet, XCircle } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { StatusBadge } from '@/components/sunat/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import SunatLayout from '@/layouts/sunat-layout';
import type { SunatStatus } from '@/types';

type Metricas = {
    empresa: { razon_social: string; ruc: string; entorno: string };
    kpis: {
        docs_hoy: number;
        docs_mes: number;
        ventas_mes: number;
        aceptados_mes: number;
        pendientes_mes: number;
        rechazados_mes: number;
        clientes_total: number;
        crecimiento_docs: number | null;
        crecimiento_ventas: number | null;
    };
    documentos_por_dia: { fecha: string; facturas: number; boletas: number; notas: number }[];
    documentos_por_tipo: { tipo: string; valor: number }[];
    estado_sunat: { estado: string; valor: number }[];
    ventas_por_mes: { mes: string; ventas: number }[];
    ultimos: { tipo: string; numero: string; cliente: string; total: number; moneda: string; estado: string; fecha: string }[];
    periodo: { inicio_mes: string; hoy: string };
};

type Props = { metricas: Metricas };

const fmtSoles = (n: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', maximumFractionDigits: 0 }).format(n);

const fmtFecha = (iso: string) => {
    const [, m, d] = iso.split('-');
    return `${d}/${m}`;
};

// Paleta categórica validada (data-viz): distinguible en daltonismo, en claro y oscuro.
const PALETTE_LIGHT = ['#F0990A', '#3599E6', '#00BA5D', '#8B5CF6', '#E63946'];
const PALETTE_DARK = ['#C98500', '#3599E6', '#00A854', '#8B5CF6', '#DE3B48'];
// Estados (semántico): color según su significado, no según ranking.
const COLOR_ESTADO: Record<string, string> = {
    Aceptado: '#00BA5D',
    Pendiente: '#F0990A',
    Enviado: '#3599E6',
    Rechazado: '#E63946',
    Anulado: '#94a3b8',
};

function useIsDark(): boolean {
    const [dark, setDark] = useState(
        () => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'),
    );
    useEffect(() => {
        const el = document.documentElement;
        const update = () => setDark(el.classList.contains('dark'));
        update();
        const obs = new MutationObserver(update);
        obs.observe(el, { attributes: true, attributeFilter: ['class'] });
        return () => obs.disconnect();
    }, []);
    return dark;
}

function KpiCard({ label, value, subvalue, growth, icon: Icon, iconColor }: {
    label: string;
    value: string;
    subvalue?: string;
    growth?: number | null;
    icon: typeof FileText;
    iconColor: string;
}) {
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between">
                <div>
                    <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
                    <div className="mt-2 text-2xl font-semibold tracking-tight">{value}</div>
                    {subvalue && <div className="mt-1 text-xs text-muted-foreground">{subvalue}</div>}
                </div>
                <div className="flex size-10 items-center justify-center rounded-lg" style={{ backgroundColor: `${iconColor}20`, color: iconColor }}>
                    <Icon className="size-5" />
                </div>
            </div>
            {growth !== null && growth !== undefined && (
                <div className="mt-3 flex items-center gap-1">
                    {growth >= 0 ? (
                        <><ArrowUpRight className="size-3 text-emerald-500" /><span className="text-xs font-medium text-emerald-500">+{growth}%</span></>
                    ) : (
                        <><ArrowDownRight className="size-3 text-red-500" /><span className="text-xs font-medium text-red-500">{growth}%</span></>
                    )}
                    <span className="text-xs text-muted-foreground">vs mes anterior</span>
                </div>
            )}
        </Card>
    );
}

function ChartCard({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) {
    return (
        <Card className="p-5">
            <div className="mb-4">
                <h3 className="text-sm font-semibold">{title}</h3>
                {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
            </div>
            {children}
        </Card>
    );
}

const TOOLTIP_STYLE = {
    backgroundColor: 'var(--popover)',
    border: '1px solid var(--border)',
    borderRadius: 6,
    fontSize: 12,
} as const;

export default function SunatDashboard({ metricas }: Props) {
    const chart = useIsDark() ? PALETTE_DARK : PALETTE_LIGHT;
    const { empresa, kpis, documentos_por_dia, documentos_por_tipo, estado_sunat, ventas_por_mes, ultimos } = metricas;

    const totalDocsMes = documentos_por_tipo.reduce((s, d) => s + d.valor, 0);
    const tiposConValor = documentos_por_tipo.filter((d) => d.valor > 0);

    return (
        <SunatLayout>
            <Head title="Panel" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                {/* Header */}
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Panel</h1>
                        <p className="text-sm text-muted-foreground">
                            {empresa.razon_social} · RUC {empresa.ruc} · {empresa.entorno}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline" size="sm" className="rounded-xl">
                            <Link href="/sunat/historial">Ver historial</Link>
                        </Button>
                        <Button asChild size="sm" className="gap-2 rounded-xl">
                            <Link href="/sunat/facturas/nueva"><Plus className="size-4" /> Nueva factura</Link>
                        </Button>
                    </div>
                </div>

                {/* KPIs */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard label="Documentos del mes" value={kpis.docs_mes.toString()} subvalue={`${kpis.docs_hoy} hoy`} growth={kpis.crecimiento_docs} icon={FileText} iconColor="#3599E6" />
                    <KpiCard label="Ventas del mes" value={fmtSoles(kpis.ventas_mes)} growth={kpis.crecimiento_ventas} icon={Wallet} iconColor="#8B5CF6" />
                    <KpiCard label="Aceptados este mes" value={kpis.aceptados_mes.toString()} subvalue={`${kpis.rechazados_mes} rechazados`} icon={CheckCircle2} iconColor="#00BA5D" />
                    <KpiCard label="Clientes" value={kpis.clientes_total.toString()} subvalue={`${kpis.pendientes_mes} en proceso`} icon={Users} iconColor="#F0990A" />
                </div>

                {/* Emisión diaria + distribución por tipo */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <ChartCard title="Emisión diaria" subtitle="Últimos 30 días · facturas / boletas / notas">
                            <ResponsiveContainer width="100%" height={260}>
                                <LineChart data={documentos_por_dia}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                                    <XAxis dataKey="fecha" tickFormatter={fmtFecha} stroke="var(--muted-foreground)" fontSize={11} />
                                    <YAxis stroke="var(--muted-foreground)" fontSize={11} allowDecimals={false} />
                                    <Tooltip contentStyle={TOOLTIP_STYLE} labelFormatter={(label) => fmtFecha(String(label))} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Line type="monotone" dataKey="facturas" stroke={chart[0]} strokeWidth={2} dot={false} name="Facturas" />
                                    <Line type="monotone" dataKey="boletas" stroke={chart[1]} strokeWidth={2} dot={false} name="Boletas" />
                                    <Line type="monotone" dataKey="notas" stroke={chart[2]} strokeWidth={2} dot={false} name="NC / ND" />
                                </LineChart>
                            </ResponsiveContainer>
                        </ChartCard>
                    </div>

                    <ChartCard title="Distribución por tipo" subtitle={`${totalDocsMes} documentos este mes`}>
                        {tiposConValor.length === 0 ? (
                            <div className="flex h-[260px] items-center justify-center text-sm text-muted-foreground">Sin documentos este mes.</div>
                        ) : (
                            <ResponsiveContainer width="100%" height={260}>
                                <PieChart>
                                    <Pie data={tiposConValor} dataKey="valor" nameKey="tipo" cx="50%" cy="50%" outerRadius={90} innerRadius={55} paddingAngle={2}>
                                        {tiposConValor.map((_, i) => (<Cell key={i} fill={chart[i % chart.length]} />))}
                                    </Pie>
                                    <Tooltip contentStyle={TOOLTIP_STYLE} />
                                    <Legend wrapperStyle={{ fontSize: 11 }} />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </ChartCard>
                </div>

                {/* Ventas por mes + estado SUNAT */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <ChartCard title="Ventas por mes" subtitle="Últimos 6 meses · comprobantes aceptados">
                            <ResponsiveContainer width="100%" height={220}>
                                <BarChart data={ventas_por_mes}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                                    <XAxis dataKey="mes" stroke="var(--muted-foreground)" fontSize={11} />
                                    <YAxis stroke="var(--muted-foreground)" fontSize={11} />
                                    <Tooltip contentStyle={TOOLTIP_STYLE} formatter={(value) => fmtSoles(Number(value))} />
                                    <Bar dataKey="ventas" radius={[6, 6, 0, 0]} fill={chart[0]} name="Ventas" />
                                </BarChart>
                            </ResponsiveContainer>
                        </ChartCard>
                    </div>

                    <ChartCard title="Estado SUNAT" subtitle="Comprobantes de este mes">
                        {estado_sunat.length === 0 ? (
                            <div className="flex h-[220px] items-center justify-center text-sm text-muted-foreground">Sin datos este mes.</div>
                        ) : (
                            <ResponsiveContainer width="100%" height={220}>
                                <PieChart>
                                    <Pie data={estado_sunat} dataKey="valor" nameKey="estado" cx="50%" cy="50%" outerRadius={75} innerRadius={45}>
                                        {estado_sunat.map((d, i) => (<Cell key={i} fill={COLOR_ESTADO[d.estado] ?? chart[i % chart.length]} />))}
                                    </Pie>
                                    <Tooltip contentStyle={TOOLTIP_STYLE} />
                                    <Legend wrapperStyle={{ fontSize: 11 }} />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </ChartCard>
                </div>

                {/* Últimos comprobantes */}
                <Card className="overflow-hidden p-0">
                    <div className="flex items-center justify-between border-b border-border/60 px-5 py-3.5">
                        <h3 className="text-sm font-semibold text-foreground">Últimos comprobantes</h3>
                        <Link href="/sunat/historial" className="text-xs font-medium text-primary hover:underline">Ver todos</Link>
                    </div>
                    {ultimos.length === 0 ? (
                        <p className="px-5 py-8 text-center text-sm text-muted-foreground">Aún no has emitido comprobantes.</p>
                    ) : (
                        <ul className="divide-y divide-border/60">
                            {ultimos.map((d, i) => (
                                <li key={i} className="flex items-center gap-3 px-5 py-3">
                                    <span className="w-20 shrink-0 text-xs font-medium text-muted-foreground">{d.tipo}</span>
                                    <span className="w-32 shrink-0 font-mono text-xs">{d.numero}</span>
                                    <span className="min-w-0 flex-1 truncate text-sm">{d.cliente}</span>
                                    <span className="shrink-0 text-sm font-semibold tabular-nums">{d.moneda === 'USD' ? '$' : 'S/'} {new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2 }).format(d.total)}</span>
                                    <span className="shrink-0"><StatusBadge status={d.estado as SunatStatus} /></span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </SunatLayout>
    );
}
