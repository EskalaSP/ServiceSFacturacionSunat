import { Head } from '@inertiajs/react';
import { BarChart3, FileSpreadsheet, FileText } from 'lucide-react';
import { useState } from 'react';
import { MultiSelect } from '@/components/multi-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SunatLayout from '@/layouts/sunat-layout';
import { armarQuery, descargarArchivo } from '@/lib/descarga';
import { firstDayOfCurrentMonthLima, todayLimaDate } from '@/lib/lima-date';

const TIPOS = [
    { value: 'factura', label: 'Factura' },
    { value: 'boleta', label: 'Boleta' },
    { value: 'nota-credito', label: 'Nota de crédito' },
    { value: 'nota-debito', label: 'Nota de débito' },
];
// El reporte no incluye pendiente/enviado/anulación en proceso; los totales se separan por ambiente y estado.
const ESTADOS = [
    { value: 'aceptado', label: 'Aceptado' },
    { value: 'aceptado_obs', label: 'Aceptado con observaciones' },
    { value: 'rechazado', label: 'Rechazado' },
    { value: 'anulado', label: 'Anulado' },
];
const AMBIENTES = [
    { value: 'produccion', label: 'Producción' },
    { value: 'prueba', label: 'Prueba' },
];

type Totales = {
    cantidad: number;
    mto_oper_gravadas: number;
    mto_oper_exoneradas: number;
    mto_oper_inafectas: number;
    mto_oper_gratuitas: number;
    mto_igv: number;
    mto_isc: number;
    mto_icbper: number;
    mto_imp_venta: number;
};

type Fila = {
    id: number;
    fecha_emision: string;
    tipo_doc: string;
    tipo_doc_nombre: string;
    numero_completo: string;
    client_num_doc: string | null;
    client_razon_social: string | null;
    mto_oper_gravadas: number;
    mto_oper_exoneradas: number;
    mto_oper_inafectas: number;
    mto_oper_gratuitas: number;
    mto_igv: number;
    mto_isc: number;
    mto_icbper: number;
    mto_imp_venta: number;
    tipo_moneda: string;
    estado_sunat: string;
    estado_grupo: 'aceptado' | 'rechazado' | 'anulado';
    estado_etiqueta: string;
    con_observaciones: boolean;
    ambiente: string;
    ambiente_etiqueta: string;
};

type Linea = {
    clave: string;
    etiqueta: string;
    estilo: 'detalle' | 'total' | 'rechazado' | 'anulado';
    totales?: Totales;
};

type Bloque = { clave: string; nombre: string; lineas: Linea[] };

type Reporte = {
    documentos: Fila[];
    bloques: Record<string, Bloque>;
    total_documentos: number;
};

const money = (n: number) =>
    n.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const CAMPOS_MONTO = [
    'mto_oper_gravadas',
    'mto_oper_exoneradas',
    'mto_oper_inafectas',
    'mto_oper_gratuitas',
    'mto_igv',
    'mto_isc',
    'mto_icbper',
    'mto_imp_venta',
] as const;

const CLASE_ESTADO: Record<string, string> = {
    rechazado: 'bg-red-500/10 text-red-600',
    anulado: 'bg-muted/60 text-muted-foreground line-through',
};

const CLASE_LINEA: Record<string, string> = {
    detalle: '',
    total: 'bg-muted font-semibold',
    rechazado: 'bg-red-500/10 font-semibold text-red-600',
    anulado: 'bg-muted/60 font-semibold text-muted-foreground',
};

function BloqueTotales({ bloque }: { bloque: Bloque }) {
    return (
        <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <div className="border-b border-border bg-muted/40 px-4 py-2 text-sm font-semibold">
                Totales {bloque.nombre}
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Concepto</TableHead>
                        <TableHead className="text-right">Cant.</TableHead>
                        <TableHead className="text-right">Gravadas</TableHead>
                        <TableHead className="text-right">Exoneradas</TableHead>
                        <TableHead className="text-right">Inafectas</TableHead>
                        <TableHead className="text-right">Gratuitas</TableHead>
                        <TableHead className="text-right">IGV</TableHead>
                        <TableHead className="text-right">ISC</TableHead>
                        <TableHead className="text-right">ICBPER</TableHead>
                        <TableHead className="text-right">
                            Importe total
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {bloque.lineas.map((l) =>
                        l.totales ? (
                            <TableRow
                                key={l.clave}
                                className={CLASE_LINEA[l.estilo]}
                            >
                                <TableCell className="whitespace-nowrap">
                                    {l.etiqueta}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {l.totales.cantidad}
                                </TableCell>
                                {CAMPOS_MONTO.map((c) => (
                                    <TableCell
                                        key={c}
                                        className="text-right tabular-nums"
                                    >
                                        {money(l.totales![c])}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ) : null,
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

export default function Reportes() {
    const [desde, setDesde] = useState(firstDayOfCurrentMonthLima());
    const [hasta, setHasta] = useState(todayLimaDate());
    const [tipos, setTipos] = useState<string[]>(['factura', 'boleta']);
    const [estados, setEstados] = useState<string[]>(
        ESTADOS.map((e) => e.value),
    );
    const [ambientes, setAmbientes] = useState<string[]>(
        AMBIENTES.map((a) => a.value),
    );
    const [loading, setLoading] = useState(false);
    const [exportando, setExportando] = useState<'pdf' | 'xlsx' | null>(null);
    const [data, setData] = useState<Reporte | null>(null);
    const [error, setError] = useState('');

    const incompleto =
        !desde ||
        !hasta ||
        tipos.length === 0 ||
        estados.length === 0 ||
        ambientes.length === 0;
    const query = () =>
        armarQuery({
            fecha_desde: desde,
            fecha_hasta: hasta,
            tipos,
            estados,
            ambientes,
        });

    const generar = async () => {
        setLoading(true);
        setError('');
        setData(null);
        try {
            const r = await fetch(
                `/sunat/reportes/registro-ventas?${query()}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const json = await r.json();
            if (json.ok) setData(json.data);
            else
                setError(
                    json.error ??
                        json.message ??
                        'No se pudo generar el reporte.',
                );
        } catch (e) {
            setError(String(e));
        } finally {
            setLoading(false);
        }
    };

    const exportar = async (formato: 'pdf' | 'excel') => {
        setExportando(formato === 'pdf' ? 'pdf' : 'xlsx');
        setError('');
        const ext = formato === 'pdf' ? 'pdf' : 'xlsx';
        const r = await descargarArchivo(
            `/sunat/reportes/registro-ventas/${formato}?${query()}`,
            `registro-ventas_${desde}_${hasta}.${ext}`,
        );
        if (!r.ok) setError(r.error);
        setExportando(null);
    };

    return (
        <SunatLayout>
            <Head title="Reportes" />
            <div className="mx-auto max-w-6xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <BarChart3 className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">
                            Registro de ventas
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Documentos de producción y prueba con sus totales
                            aceptados por separado.
                        </p>
                    </div>
                </header>

                <section className="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="grid gap-1.5">
                            <Label>Desde</Label>
                            <Input
                                type="date"
                                value={desde}
                                onChange={(e) => setDesde(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Hasta</Label>
                            <Input
                                type="date"
                                value={hasta}
                                onChange={(e) => setHasta(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Tipo de documento</Label>
                            <MultiSelect
                                value={tipos}
                                onChange={setTipos}
                                options={TIPOS}
                                placeholder="Ninguno"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Estado</Label>
                            <MultiSelect
                                value={estados}
                                onChange={setEstados}
                                options={ESTADOS}
                                placeholder="Ninguno"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Ambiente</Label>
                            <MultiSelect
                                value={ambientes}
                                onChange={setAmbientes}
                                options={AMBIENTES}
                                placeholder="Ninguno"
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            onClick={generar}
                            disabled={loading || incompleto}
                        >
                            {loading ? 'Generando…' : 'Generar'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => exportar('pdf')}
                            disabled={incompleto || exportando !== null}
                        >
                            <FileText className="size-4" />{' '}
                            {exportando === 'pdf'
                                ? 'Exportando…'
                                : 'Exportar PDF'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => exportar('excel')}
                            disabled={incompleto || exportando !== null}
                        >
                            <FileSpreadsheet className="size-4" />{' '}
                            {exportando === 'xlsx'
                                ? 'Exportando…'
                                : 'Exportar Excel'}
                        </Button>
                    </div>
                </section>

                {error && (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {data && (
                    <section className="space-y-3">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-xl border border-border bg-card px-4 py-3">
                                <div className="text-[10px] text-muted-foreground uppercase">
                                    Documentos
                                </div>
                                <div className="text-lg font-semibold tabular-nums">
                                    {data.total_documentos}
                                </div>
                            </div>
                            {Object.values(data.bloques).map((b) => {
                                const aceptados = b.lineas.find(
                                    (l) => l.clave === 'aceptados',
                                )?.totales;
                                return aceptados ? (
                                    <div
                                        key={b.clave}
                                        className="rounded-xl border border-border bg-card px-4 py-3"
                                    >
                                        <div className="text-[10px] text-muted-foreground uppercase">
                                            Total aceptados {b.nombre}
                                        </div>
                                        <div className="text-lg font-semibold tabular-nums">
                                            {money(aceptados.mto_imp_venta)}
                                        </div>
                                    </div>
                                ) : null;
                            })}
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-border bg-card">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Número</TableHead>
                                        <TableHead>Doc. cliente</TableHead>
                                        <TableHead>Cliente</TableHead>
                                        <TableHead className="text-right">
                                            Gravadas
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Exoneradas
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Inafectas
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Gratuitas
                                        </TableHead>
                                        <TableHead className="text-right">
                                            IGV
                                        </TableHead>
                                        <TableHead className="text-right">
                                            ISC
                                        </TableHead>
                                        <TableHead className="text-right">
                                            ICBPER
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                        <TableHead>Mon.</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Ambiente</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.documentos.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={16}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No hay documentos con estos
                                                filtros.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {data.documentos.map((d) => (
                                        <TableRow
                                            key={`${d.tipo_doc}-${d.id}`}
                                            className={
                                                CLASE_ESTADO[d.estado_grupo]
                                            }
                                        >
                                            <TableCell>
                                                {d.fecha_emision}
                                            </TableCell>
                                            <TableCell>
                                                {d.tipo_doc_nombre}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {d.numero_completo}
                                            </TableCell>
                                            <TableCell>
                                                {d.client_num_doc ?? '—'}
                                            </TableCell>
                                            <TableCell className="max-w-56 truncate">
                                                {d.client_razon_social ?? '—'}
                                            </TableCell>
                                            {CAMPOS_MONTO.map((c) => (
                                                <TableCell
                                                    key={c}
                                                    className="text-right tabular-nums"
                                                >
                                                    {money(d[c])}
                                                </TableCell>
                                            ))}
                                            <TableCell>
                                                {d.tipo_moneda}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {d.estado_etiqueta}
                                            </TableCell>
                                            <TableCell>
                                                {d.ambiente_etiqueta}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {Object.values(data.bloques).map((b) => (
                            <BloqueTotales key={b.clave} bloque={b} />
                        ))}
                    </section>
                )}
            </div>
        </SunatLayout>
    );
}
