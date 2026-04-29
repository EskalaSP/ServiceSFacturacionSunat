import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Loader2, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SunatLayout from '@/layouts/sunat-layout';
import type { SerieSunat, TenantSunat } from '@/types';

type MotivoND = { codigo: string; descripcion: string };

type DocOriginal = {
    id: number;
    tipo_doc: string;
    serie: string;
    correlativo: number;
    numero: string;
    cliente: string;
    total: number;
    moneda: string;
    items: { descripcion: string; cantidad: number; precio_unitario: number; unidad: string }[];
};

type Props = {
    motivos: MotivoND[];
    series: SerieSunat[];
    doc_original: DocOriginal | null;
    tenant: Pick<TenantSunat, 'ruc' | 'razon_social'>;
};

type ItemND = {
    descripcion: string;
    unidad: string;
    cantidad: number;
    precio_unitario: number;
};

function fmt(n: number) {
    return new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2 }).format(n);
}

export default function NuevaNotaDebito({ motivos, series, doc_original, tenant }: Props) {
    const [busqueda, setBusqueda]       = useState('');
    const [resultados, setResultados]   = useState<DocOriginal[]>([]);
    const [buscando, setBuscando]       = useState(false);
    const [docSeleccionado, setDocSel]  = useState<DocOriginal | null>(doc_original);

    const [serie, setSerie]             = useState(series[0]?.serie ?? 'FD01');
    const [fecha, setFecha]             = useState(new Date().toISOString().split('T')[0]);
    const [motivo, setMotivo]           = useState('');
    const [desMotivo, setDesMotivo]     = useState('');
    const [items, setItems]             = useState<ItemND[]>(
        doc_original?.items.map((i) => ({ ...i })) ?? [{ descripcion: '', unidad: 'ZZ', cantidad: 1, precio_unitario: 0 }]
    );
    const [submitting, setSubmitting]   = useState(false);
    const [errors, setErrors]           = useState<Record<string, string>>({});

    async function buscarDocumento() {
        if (!busqueda.trim()) return;
        setBuscando(true);
        try {
            const res = await fetch(`/sunat/buscar-documento?q=${encodeURIComponent(busqueda)}`, {
                headers: { Accept: 'application/json' },
            });
            setResultados(await res.json());
        } finally {
            setBuscando(false);
        }
    }

    function seleccionarDoc(doc: DocOriginal) {
        setDocSel(doc);
        setItems(doc.items.map((i) => ({ ...i })));
        setResultados([]);
        setBusqueda('');
    }

    function updateItem(i: number, field: keyof ItemND, value: string | number) {
        setItems((prev) => prev.map((it, idx) => idx === i ? { ...it, [field]: value } : it));
    }

    const totalND = items.reduce((sum, it) => sum + it.cantidad * it.precio_unitario * 1.18, 0);

    function validate() {
        const e: Record<string, string> = {};
        if (!docSeleccionado)                              e.doc    = 'Selecciona el comprobante al que agregas el cargo.';
        if (!motivo)                                       e.motivo = 'Selecciona el motivo.';
        if (items.some((it) => !it.descripcion))           e.items  = 'Todos los ítems deben tener descripción.';
        if (items.some((it) => it.precio_unitario <= 0))   e.items  = 'El precio unitario debe ser mayor a 0.';
        setErrors(e);
        return Object.keys(e).length === 0;
    }

    function handleSubmit() {
        if (!validate() || !docSeleccionado) return;
        setSubmitting(true);

        router.post('/sunat/nota-debito', {
            serie,
            fecha_emision:      fecha,
            tipo_moneda:        docSeleccionado.moneda ?? 'PEN',
            cod_motivo:         motivo,
            des_motivo:         desMotivo || (motivos.find((m) => m.codigo === motivo)?.descripcion ?? motivo),
            doc_afectado_tipo:  docSeleccionado.tipo_doc,
            doc_afectado_serie: docSeleccionado.serie,
            doc_afectado_corr:  docSeleccionado.correlativo,
            cliente: {
                tipo_doc:     docSeleccionado.tipo_doc === '03' ? '1' : '6',
                num_doc:      '',
                razon_social: docSeleccionado.cliente,
            },
            items: items.map((it) => ({
                descripcion:     it.descripcion,
                unidad:          it.unidad,
                cantidad:        it.cantidad,
                precio_unitario: it.precio_unitario,
                tip_afe_igv:     '10',
            })),
        }, { onFinish: () => setSubmitting(false) });
    }

    return (
        <SunatLayout>
            <Head title="Nueva Nota de Débito" />

            <div className="mx-auto max-w-3xl p-4 md:p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Nueva Nota de Débito</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Agrega un cargo adicional a una factura o boleta ya emitida (intereses, penalidades, ajustes de valor).
                    </p>
                </div>

                <div className="flex flex-col gap-6">

                    {/* ── BUSCAR DOCUMENTO ORIGINAL ── */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Comprobante de referencia</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex gap-2">
                                <Input
                                    placeholder="Buscar por número (F001-00000001), cliente..."
                                    value={busqueda}
                                    onChange={(e) => setBusqueda(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && buscarDocumento()}
                                    className="flex-1"
                                />
                                <Button type="button" variant="outline" onClick={buscarDocumento} disabled={buscando}>
                                    {buscando ? <Loader2 className="size-4 animate-spin" /> : <Search className="size-4" />}
                                </Button>
                            </div>

                            {errors.doc && <p className="text-xs text-destructive">{errors.doc}</p>}

                            {resultados.length > 0 && (
                                <div className="rounded-md border">
                                    {resultados.map((doc) => (
                                        <button
                                            key={`${doc.tipo_doc}-${doc.id}`}
                                            type="button"
                                            className="flex w-full items-center justify-between border-b px-4 py-3 text-left last:border-0 hover:bg-muted/50"
                                            onClick={() => seleccionarDoc(doc)}
                                        >
                                            <div>
                                                <span className="text-sm font-medium">{doc.numero}</span>
                                                <span className="ml-2 text-xs text-muted-foreground">{doc.cliente}</span>
                                            </div>
                                            <span className="text-sm font-medium">S/ {fmt(doc.total)}</span>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {docSeleccionado && (
                                <div className="flex items-center justify-between rounded-lg border border-primary/30 bg-primary/5 px-4 py-3">
                                    <div>
                                        <p className="text-sm font-medium">{docSeleccionado.numero}</p>
                                        <p className="text-xs text-muted-foreground">{docSeleccionado.cliente}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-semibold">S/ {fmt(docSeleccionado.total)}</p>
                                        <button
                                            type="button"
                                            onClick={() => { setDocSel(null); setItems([{ descripcion: '', unidad: 'ZZ', cantidad: 1, precio_unitario: 0 }]); }}
                                            className="text-xs text-muted-foreground hover:text-destructive"
                                        >
                                            Cambiar
                                        </button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* ── DATOS ND ── */}
                    <Card>
                        <CardHeader><CardTitle className="text-base">Datos de la nota</CardTitle></CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label>Serie</Label>
                                    <select
                                        value={serie}
                                        onChange={(e) => setSerie(e.target.value)}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        {series.map((s) => (
                                            <option key={s.id} value={s.serie}>{s.serie}</option>
                                        ))}
                                        {series.length === 0 && <option value="FD01">FD01</option>}
                                    </select>
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Fecha de emisión</Label>
                                    <Input type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} />
                                </div>
                                <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
                                    <Label>Motivo</Label>
                                    <select
                                        value={motivo}
                                        onChange={(e) => {
                                            setMotivo(e.target.value);
                                            const m = motivos.find((m) => m.codigo === e.target.value);
                                            if (m) setDesMotivo(m.descripcion);
                                        }}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                        aria-invalid={!!errors.motivo}
                                    >
                                        <option value="">— Selecciona motivo —</option>
                                        {motivos.map((m) => (
                                            <option key={m.codigo} value={m.codigo}>
                                                {m.codigo} — {m.descripcion}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.motivo && <p className="text-xs text-destructive">{errors.motivo}</p>}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* ── ÍTEMS ── */}
                    <Card>
                        <CardHeader><CardTitle className="text-base">Conceptos a cobrar adicionalmente</CardTitle></CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {errors.items && <p className="text-xs text-destructive">{errors.items}</p>}

                            {items.map((item, i) => (
                                <div key={i} className="grid grid-cols-4 gap-2 rounded-md bg-muted/20 p-3">
                                    <div className="col-span-4 flex flex-col gap-1">
                                        <Label className="text-xs">Descripción del cargo</Label>
                                        <Input
                                            value={item.descripcion}
                                            onChange={(e) => updateItem(i, 'descripcion', e.target.value)}
                                            className="h-8 text-xs"
                                            placeholder="Ej: Intereses por pago tardío — Octubre 2025"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <Label className="text-xs">Cantidad</Label>
                                        <Input
                                            type="number" min={0.001} step="0.001"
                                            value={item.cantidad}
                                            onChange={(e) => updateItem(i, 'cantidad', parseFloat(e.target.value) || 0)}
                                            className="h-8 text-right text-xs"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <Label className="text-xs">P. Unit. (sin IGV)</Label>
                                        <Input
                                            type="number" min={0} step="0.01"
                                            value={item.precio_unitario || ''}
                                            onChange={(e) => updateItem(i, 'precio_unitario', parseFloat(e.target.value) || 0)}
                                            className="h-8 text-right text-xs"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <Label className="text-xs">U.M.</Label>
                                        <select
                                            value={item.unidad}
                                            onChange={(e) => updateItem(i, 'unidad', e.target.value)}
                                            className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                        >
                                            <option value="ZZ">ZZ — Servicio</option>
                                            <option value="NIU">NIU — Unidad</option>
                                            <option value="HUR">HUR — Hora</option>
                                            <option value="DAY">DAY — Día</option>
                                            <option value="MON">MON — Mes</option>
                                        </select>
                                    </div>
                                    <div className="flex flex-col justify-end gap-1">
                                        <Label className="invisible text-xs">X</Label>
                                        {items.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => setItems((prev) => prev.filter((_, j) => j !== i))}
                                                className="flex h-8 items-center justify-center rounded-md border text-muted-foreground hover:border-destructive hover:text-destructive"
                                            >
                                                ×
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}

                            <Button
                                type="button" variant="outline" size="sm"
                                onClick={() => setItems((prev) => [...prev, { descripcion: '', unidad: 'ZZ', cantidad: 1, precio_unitario: 0 }])}
                            >
                                + Agregar concepto
                            </Button>

                            <div className="ml-auto flex items-center gap-4 rounded-lg bg-muted/40 px-4 py-2">
                                <span className="text-sm text-muted-foreground">Total Nota de Débito (inc. IGV):</span>
                                <span className="text-base font-semibold">S/ {fmt(totalND)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* ── ACCIONES ── */}
                    <div className="flex flex-wrap gap-3 pb-6">
                        <Button onClick={handleSubmit} disabled={submitting}>
                            {submitting && <Loader2 className="mr-2 size-4 animate-spin" />}
                            Emitir Nota de Débito
                        </Button>
                        <Button variant="outline" onClick={() => router.visit('/sunat/historial')} disabled={submitting}>
                            Cancelar
                        </Button>
                    </div>
                </div>
            </div>
        </SunatLayout>
    );
}
