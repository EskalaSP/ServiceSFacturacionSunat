import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    CheckCircle2,
    FileText,
    Wallet,
} from 'lucide-react';
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
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

type Kpis = {
    empresas_total: number;
    empresas_activas: number;
    docs_hoy: number;
    docs_mes: number;
    docs_mes_anterior: number;
    ventas_mes: number;
    ventas_mes_anterior: number;
    crecimiento_docs: number | null;
    crecimiento_ventas: number | null;
};

type Metricas = {
    kpis: Kpis;
    documentos_por_dia: { fecha: string; facturas: number; boletas: number; notas: number }[];
    documentos_por_tipo: { tipo: string; valor: number }[];
    empresas_por_plan: { plan: string; total: number }[];
    empresas_por_regimen: { regimen: string; total: number }[];
    estado_sunat: { estado: string; valor: number }[];
    top_empresas: { ruc: string; razon_social: string; total_ventas: number; total_docs: number }[];
    empresas_por_entorno: { entorno: string; total: number }[];
    periodo: { inicio_mes: string; hoy: string };
};

type Props = {
    esAdmin: boolean;
    metricas: Metricas | null;
};

const fmtSoles = (n: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', maximumFractionDigits: 0 }).format(n);

const fmtFecha = (iso: string) => {
    const [, m, d] = iso.split('-');
    return `${d}/${m}`;
};

// Paleta consistente con el tema
const COLORS_TIPO = ['#FAA307', '#BAC5AC', '#8AA894', '#9FB88E', '#C7D4B8'];
const COLORS_PLAN = ['#94A3B8', '#FAA307', '#3B82F6'];
const COLORS_REGIMEN = ['#FAA307', '#BAC5AC', '#8AA894'];
const COLORS_ESTADO = ['#10B981', '#EF4444', '#3B82F6', '#94A3B8'];
const COLORS_ENTORNO = ['#F59E0B', '#10B981'];

function KpiCard({
    label,
    value,
    subvalue,
    growth,
    icon: Icon,
    iconColor,
}: {
    label: string;
    value: string;
    subvalue?: string;
    growth?: number | null;
    icon: typeof Building2;
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
                <div
                    className="flex size-10 items-center justify-center rounded-lg"
                    style={{ backgroundColor: `${iconColor}20`, color: iconColor }}
                >
                    <Icon className="size-5" />
                </div>
            </div>
            {growth !== null && growth !== undefined && (
                <div className="mt-3 flex items-center gap-1">
                    {growth >= 0 ? (
                        <>
                            <ArrowUpRight className="size-3 text-emerald-500" />
                            <span className="text-xs font-medium text-emerald-500">+{growth}%</span>
                        </>
                    ) : (
                        <>
                            <ArrowDownRight className="size-3 text-red-500" />
                            <span className="text-xs font-medium text-red-500">{growth}%</span>
                        </>
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

function DashboardPlaceholder() {
    return (
        <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                {[0, 1, 2].map((i) => (
                    <div
                        key={i}
                        className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70"
                    >
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Dashboard({ esAdmin, metricas }: Props) {
    if (!esAdmin || !metricas) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <DashboardPlaceholder />
            </AppLayout>
        );
    }

    const { kpis, documentos_por_dia, documentos_por_tipo, empresas_por_plan, empresas_por_regimen, estado_sunat, top_empresas, empresas_por_entorno } = metricas;

    const totalDocsMes = documentos_por_tipo.reduce((s, d) => s + d.valor, 0);
    const totalEmpresasEntorno = empresas_por_entorno.reduce((s, d) => s + d.total, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-end justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Panel administrativo</h1>
                        <p className="text-sm text-muted-foreground">
                            Estadísticas globales de la operación · mes actual
                        </p>
                    </div>
                </div>

                {/* KPIs */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        label="Empresas"
                        value={kpis.empresas_total.toString()}
                        subvalue={`${kpis.empresas_activas} activas`}
                        icon={Building2}
                        iconColor="#FAA307"
                    />
                    <KpiCard
                        label="Documentos hoy"
                        value={kpis.docs_hoy.toString()}
                        subvalue={`${kpis.docs_mes} este mes`}
                        icon={FileText}
                        iconColor="#3B82F6"
                    />
                    <KpiCard
                        label="Ventas del mes"
                        value={fmtSoles(kpis.ventas_mes)}
                        growth={kpis.crecimiento_ventas}
                        icon={Wallet}
                        iconColor="#F59E0B"
                    />
                    <KpiCard
                        label="Documentos del mes"
                        value={kpis.docs_mes.toString()}
                        growth={kpis.crecimiento_docs}
                        icon={CheckCircle2}
                        iconColor="#10B981"
                    />
                </div>

                {/* Grid principal de gráficos */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Línea: docs por día */}
                    <div className="lg:col-span-2">
                        <ChartCard title="Emisión diaria" subtitle="Últimos 30 días · facturas / boletas / notas">
                            <ResponsiveContainer width="100%" height={260}>
                                <LineChart data={documentos_por_dia}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                                    <XAxis
                                        dataKey="fecha"
                                        tickFormatter={fmtFecha}
                                        stroke="var(--muted-foreground)"
                                        fontSize={11}
                                    />
                                    <YAxis stroke="var(--muted-foreground)" fontSize={11} />
                                    <Tooltip
                                        contentStyle={{
                                            backgroundColor: 'var(--popover)',
                                            border: '1px solid var(--border)',
                                            borderRadius: 6,
                                            fontSize: 12,
                                        }}
                                        labelFormatter={fmtFecha}
                                    />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Line type="monotone" dataKey="facturas" stroke="#FAA307" strokeWidth={2} dot={false} name="Facturas" />
                                    <Line type="monotone" dataKey="boletas" stroke="#BAC5AC" strokeWidth={2} dot={false} name="Boletas" />
                                    <Line type="monotone" dataKey="notas" stroke="#F59E0B" strokeWidth={2} dot={false} name="NC / ND" />
                                </LineChart>
                            </ResponsiveContainer>
                        </ChartCard>
                    </div>

                    {/* Donut: distribución por tipo */}
                    <ChartCard title="Distribución por tipo" subtitle={`${totalDocsMes} documentos este mes`}>
                        <ResponsiveContainer width="100%" height={260}>
                            <PieChart>
                                <Pie
                                    data={documentos_por_tipo.filter((d) => d.valor > 0)}
                                    dataKey="valor"
                                    nameKey="tipo"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={90}
                                    innerRadius={55}
                                    paddingAngle={2}
                                >
                                    {documentos_por_tipo.map((_, i) => (
                                        <Cell key={i} fill={COLORS_TIPO[i % COLORS_TIPO.length]} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'var(--popover)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 6,
                                        fontSize: 12,
                                    }}
                                />
                                <Legend wrapperStyle={{ fontSize: 11 }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </div>

                {/* Fila de 3 gráficos */}
                <div className="grid gap-4 md:grid-cols-3">
                    {/* Barras: empresas por plan */}
                    <ChartCard title="Empresas por plan" subtitle="Distribución actual">
                        <ResponsiveContainer width="100%" height={200}>
                            <BarChart data={empresas_por_plan}>
                                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                                <XAxis dataKey="plan" stroke="var(--muted-foreground)" fontSize={11} />
                                <YAxis stroke="var(--muted-foreground)" fontSize={11} />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'var(--popover)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 6,
                                        fontSize: 12,
                                    }}
                                />
                                <Bar dataKey="total" radius={[6, 6, 0, 0]}>
                                    {empresas_por_plan.map((_, i) => (
                                        <Cell key={i} fill={COLORS_PLAN[i % COLORS_PLAN.length]} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>

                    {/* Donut: régimen */}
                    <ChartCard title="Régimen tributario" subtitle="Empresas por régimen">
                        <ResponsiveContainer width="100%" height={200}>
                            <PieChart>
                                <Pie
                                    data={empresas_por_regimen}
                                    dataKey="total"
                                    nameKey="regimen"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={70}
                                    innerRadius={45}
                                >
                                    {empresas_por_regimen.map((_, i) => (
                                        <Cell key={i} fill={COLORS_REGIMEN[i % COLORS_REGIMEN.length]} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'var(--popover)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 6,
                                        fontSize: 12,
                                    }}
                                />
                                <Legend wrapperStyle={{ fontSize: 10 }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </ChartCard>

                    {/* Donut: estado SUNAT */}
                    <ChartCard title="Estado SUNAT" subtitle="Documentos del mes">
                        <ResponsiveContainer width="100%" height={200}>
                            <PieChart>
                                <Pie
                                    data={estado_sunat.filter((d) => d.valor > 0)}
                                    dataKey="valor"
                                    nameKey="estado"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={70}
                                    innerRadius={45}
                                >
                                    {estado_sunat.map((_, i) => (
                                        <Cell key={i} fill={COLORS_ESTADO[i % COLORS_ESTADO.length]} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'var(--popover)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 6,
                                        fontSize: 12,
                                    }}
                                />
                                <Legend wrapperStyle={{ fontSize: 10 }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </div>

                {/* Fila final: top empresas + entorno */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <ChartCard title="Top 5 empresas del mes" subtitle="Por ventas SUNAT aceptadas">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-xs uppercase text-muted-foreground">
                                            <th className="pb-2 text-left font-medium">#</th>
                                            <th className="pb-2 text-left font-medium">RUC</th>
                                            <th className="pb-2 text-left font-medium">Razón social</th>
                                            <th className="pb-2 text-right font-medium">Docs</th>
                                            <th className="pb-2 text-right font-medium">Ventas</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {top_empresas.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="py-6 text-center text-muted-foreground">
                                                    Sin datos este mes.
                                                </td>
                                            </tr>
                                        ) : (
                                            top_empresas.map((emp, i) => (
                                                <tr key={emp.ruc} className="hover:bg-muted/30">
                                                    <td className="py-2.5">
                                                        <Badge variant="secondary" className="font-mono text-xs">
                                                            {i + 1}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2.5 font-mono text-xs">{emp.ruc}</td>
                                                    <td className="py-2.5">
                                                        <Link
                                                            href={`/admin/empresas?buscar=${emp.ruc}`}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {emp.razon_social}
                                                        </Link>
                                                    </td>
                                                    <td className="py-2.5 text-right font-mono text-xs">
                                                        {emp.total_docs}
                                                    </td>
                                                    <td className="py-2.5 text-right font-mono font-semibold">
                                                        {fmtSoles(emp.total_ventas)}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </ChartCard>
                    </div>

                    <ChartCard title="Entorno SUNAT" subtitle={`${totalEmpresasEntorno} empresas totales`}>
                        <ResponsiveContainer width="100%" height={200}>
                            <PieChart>
                                <Pie
                                    data={empresas_por_entorno}
                                    dataKey="total"
                                    nameKey="entorno"
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={70}
                                    innerRadius={45}
                                    label={(entry) => `${entry.total}`}
                                >
                                    {empresas_por_entorno.map((_, i) => (
                                        <Cell key={i} fill={COLORS_ENTORNO[i % COLORS_ENTORNO.length]} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'var(--popover)',
                                        border: '1px solid var(--border)',
                                        borderRadius: 6,
                                        fontSize: 12,
                                    }}
                                />
                                <Legend wrapperStyle={{ fontSize: 11 }} />
                            </PieChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </div>
            </div>
        </AppLayout>
    );
}
