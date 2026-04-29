import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { AlertCircle, Building2, Loader2, Plus, Search, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import type { ClienteSunat, TenantSunat } from '@/types';

// ─── Catálogos ────────────────────────────────────────────────────────────────

const TIPOS_IGV = [
    { code: '10', label: 'Gravado 18%' },
    { code: '20', label: 'Exonerado' },
    { code: '30', label: 'Inafecto' },
];

const UNIDADES = [
    { code: 'ZZ',  label: 'ZZ — Servicio' },
    { code: 'NIU', label: 'NIU — Unidad' },
    { code: 'HUR', label: 'HUR — Hora' },
    { code: 'DAY', label: 'DAY — Día' },
    { code: 'MON', label: 'MON — Mes' },
    { code: 'KGM', label: 'KGM — Kilogramo' },
    { code: 'MTR', label: 'MTR — Metro' },
];

// ─── Types ────────────────────────────────────────────────────────────────────

type ItemRow = {
    descripcion: string;
    unidad: string;
    cantidad: number;
    precio_unitario: number;
    descuento_pct: number;
    tip_afe_igv: string;
};

type Props = {
    tenant: TenantSunat;
    clientes: ClienteSunat[];
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

function calcItem(item: ItemRow) {
    const base = item.cantidad * item.precio_unitario * (1 - item.descuento_pct / 100);
    const igv  = item.tip_afe_igv === '10' ? base * 0.18 : 0;
    return { base, igv, total: base + igv };
}

function fmt(n: number, dec = 2) {
    return new Intl.NumberFormat('es-PE', { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(n);
}

function fmtDate(iso: string) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function defaultItem(): ItemRow {
    return { descripcion: '', unidad: 'ZZ', cantidad: 1, precio_unitario: 0, descuento_pct: 0, tip_afe_igv: '10' };
}

// ─── Componente ──────────────────────────────────────────────────────────────

export default function NuevaCotizacion({ tenant, clientes }: Props) {

    const [fecha, setFecha]               = useState(new Date().toISOString().split('T')[0]);
    const [fechaVenc, setFechaVenc]        = useState('');
    const [moneda, setMoneda]              = useState<'PEN' | 'USD'>('PEN');
    const [observacion, setObservacion]    = useState('');

    const [clienteTipoDoc, setClienteTipoDoc]   = useState('6');
    const [clienteNumDoc, setClienteNumDoc]     = useState('');
    const [clienteNombre, setClienteNombre]     = useState('');
    const [clienteDireccion, setClienteDireccion] = useState('');
    const [clienteEmail, setClienteEmail]       = useState('');
    const [clienteSearch, setClienteSearch]     = useState('');
    const [clienteResults, setClienteResults]   = useState<ClienteSunat[]>([]);
    const [searchingCliente, setSearchingCliente] = useState(false);
    const searchRef = useRef<ReturnType<typeof setTimeout>>();

    const [items, setItems]   = useState<ItemRow[]>([defaultItem()]);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // ── Búsqueda de cliente ──
    const searchCliente = useCallback((q: string) => {
        clearTimeout(searchRef.current);
        setClienteSearch(q);
        if (q.length < 2) { setClienteResults([]); return; }
        searchRef.current = setTimeout(async () => {
            setSearchingCliente(true);
            try {
                const res = await fetch(`/sunat/buscar-cliente?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                setClienteResults(await res.json());
            } finally { setSearchingCliente(false); }
        }, 300);
    }, []);

    function selectCliente(c: ClienteSunat) {
        setClienteTipoDoc(c.tipo_documento);
        setClienteNumDoc(c.numero_documento);
        setClienteNombre(c.razon_social);
        setClienteDireccion(c.direccion ?? '');
        setClienteEmail(c.email ?? '');
        setClienteSearch('');
        setClienteResults([]);
    }

    // ── Ítems ──
    function updateItem<K extends keyof ItemRow>(i: number, field: K, value: ItemRow[K]) {
        setItems((prev) => prev.map((it, idx) => idx === i ? { ...it, [field]: value } : it));
    }

    // ── Totales ──
    const totals = useMemo(() => {
        let gravadas = 0, exoneradas = 0, inafectas = 0, igvTotal = 0;
        for (const it of items) {
            const { base, igv } = calcItem(it);
            if      (it.tip_afe_igv === '10') { gravadas += base; igvTotal += igv; }
            else if (it.tip_afe_igv === '20') exoneradas += base;
            else if (it.tip_afe_igv === '30') inafectas  += base;
        }
        const valorVenta = gravadas + exoneradas + inafectas;
        const total      = valorVenta + igvTotal;
        return { gravadas, exoneradas, inafectas, igvTotal, valorVenta, total };
    }, [items]);

    const simbolo = moneda === 'PEN' ? 'S/' : '$';

    // ── Validación ──
    function validate() {
        const e: Record<string, string> = {};
        if (!clienteNumDoc)  e.cliente = 'Número de documento requerido.';
        if (!clienteNombre)  e.cliente_nombre = 'Razón social requerida.';
        if (items.some((it) => !it.descripcion)) e.items = 'Todos los ítems deben tener descripción.';
        setErrors(e);
        return Object.keys(e).length === 0;
    }

    // ── Envío ──
    function submit() {
        if (!validate()) return;
        setSubmitting(true);

        router.post('/sunat/cotizaciones', {
            fecha_emision:     fecha,
            fecha_vencimiento: fechaVenc || null,
            tipo_moneda:       moneda,
            cliente: {
                tipo_doc:     clienteTipoDoc,
                num_doc:      clienteNumDoc,
                razon_social: clienteNombre,
                direccion:    clienteDireccion || undefined,
                email:        clienteEmail     || undefined,
            },
            items: items.map((it) => ({
                descripcion:     it.descripcion,
                unidad:          it.unidad,
                cantidad:        it.cantidad,
                precio_unitario: it.precio_unitario,
                tip_afe_igv:     it.tip_afe_igv,
                descuentos: it.descuento_pct > 0 ? [{
                    cod_tipo:   '00',
                    factor:     it.descuento_pct / 100,
                    monto:      it.precio_unitario * it.cantidad * (it.descuento_pct / 100),
                    monto_base: it.precio_unitario * it.cantidad,
                }] : undefined,
            })),
            observacion: observacion || null,
        }, { onFinish: () => setSubmitting(false) });
    }

    return (
        <SunatLayout>
            <Head title="Nueva Cotización" />

            <div className="mx-auto max-w-[1400px]">
                <div className="mb-5 flex items-center gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">Nueva Cotización</h1>
                    {tenant.environment !== 'produccion' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800">
                            <AlertCircle className="size-3" /> Ambiente Beta
                        </span>
                    )}
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_380px]">

                    {/* ══ FORMULARIO ══ */}
                    <div className="flex flex-col gap-5">

                        {/* ── 1. ENCABEZADO ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold">Datos de la cotización</span>
                            </div>
                            <div className="grid gap-4 p-5 sm:grid-cols-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Fecha de emisión</Label>
                                    <Input type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Válida hasta (opcional)</Label>
                                    <Input type="date" value={fechaVenc} min={fecha} onChange={(e) => setFechaVenc(e.target.value)} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Moneda</Label>
                                    <select
                                        value={moneda}
                                        onChange={(e) => setMoneda(e.target.value as 'PEN' | 'USD')}
                                        className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none"
                                    >
                                        <option value="PEN">PEN — Soles</option>
                                        <option value="USD">USD — Dólares</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        {/* ── 2. CLIENTE ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="flex items-center gap-2 border-b border-border/60 px-5 py-3.5">
                                <Building2 className="size-4 text-muted-foreground" />
                                <span className="text-sm font-semibold">Cliente</span>
                            </div>
                            <div className="p-5">
                                <div className="relative mb-4">
                                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        className="h-10 rounded-xl pl-9"
                                        placeholder="Buscar por RUC, DNI o razón social..."
                                        value={clienteSearch}
                                        onChange={(e) => searchCliente(e.target.value)}
                                    />
                                    {searchingCliente && <Loader2 className="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />}
                                    {clienteResults.length > 0 && (
                                        <div className="absolute z-50 mt-1 w-full rounded-xl border border-border bg-popover shadow-soft">
                                            {clienteResults.map((c) => (
                                                <button key={c.id} type="button"
                                                    className="flex w-full flex-col px-4 py-2.5 text-left first:rounded-t-xl last:rounded-b-xl hover:bg-secondary"
                                                    onClick={() => selectCliente(c)}
                                                >
                                                    <span className="text-sm font-medium">{c.razon_social}</span>
                                                    <span className="text-xs text-muted-foreground">{c.tipo_documento === '6' ? 'RUC' : 'DNI'} {c.numero_documento}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">Tipo doc.</Label>
                                        <select value={clienteTipoDoc} onChange={(e) => setClienteTipoDoc(e.target.value)}
                                            className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none">
                                            <option value="6">RUC</option>
                                            <option value="1">DNI</option>
                                            <option value="4">Carné Extranjería</option>
                                            <option value="7">Pasaporte</option>
                                            <option value="0">Otros</option>
                                        </select>
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">Número de documento</Label>
                                        <Input value={clienteNumDoc} onChange={(e) => setClienteNumDoc(e.target.value)}
                                            className={`h-10 rounded-xl ${errors.cliente ? 'border-destructive' : ''}`} />
                                        {errors.cliente && <p className="text-xs text-destructive">{errors.cliente}</p>}
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">Razón social</Label>
                                        <Input value={clienteNombre} onChange={(e) => setClienteNombre(e.target.value)}
                                            className={`h-10 rounded-xl ${errors.cliente_nombre ? 'border-destructive' : ''}`} />
                                        {errors.cliente_nombre && <p className="text-xs text-destructive">{errors.cliente_nombre}</p>}
                                    </div>
                                    <div className="flex flex-col gap-1.5 sm:col-span-2">
                                        <Label className="text-xs font-medium text-muted-foreground">Dirección</Label>
                                        <Input value={clienteDireccion} onChange={(e) => setClienteDireccion(e.target.value)} className="h-10 rounded-xl" />
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">Email</Label>
                                        <Input type="email" value={clienteEmail} onChange={(e) => setClienteEmail(e.target.value)} className="h-10 rounded-xl" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        {/* ── 3. ÍTEMS ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="flex items-center justify-between border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold">Servicios / Productos</span>
                                <button type="button" onClick={() => setItems((p) => [...p, defaultItem()])}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-border bg-secondary px-3 py-1.5 text-xs font-medium hover:bg-muted">
                                    <Plus className="size-3.5" /> Agregar ítem
                                </button>
                            </div>
                            {errors.items && <div className="border-b border-destructive/20 bg-destructive/5 px-5 py-2 text-xs text-destructive">{errors.items}</div>}
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border/60 bg-muted/30">
                                        <tr>
                                            <th className="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Descripción</th>
                                            <th className="px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">U.M.</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Cant.</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">P.Unit.</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Desc.%</th>
                                            <th className="px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">IGV</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Total</th>
                                            <th className="px-2 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item, i) => {
                                            const { total } = calcItem(item);
                                            return (
                                                <tr key={i} className="border-b border-border/40 last:border-0 hover:bg-muted/20">
                                                    <td className="px-3 py-2.5">
                                                        <Input value={item.descripcion} onChange={(e) => updateItem(i, 'descripcion', e.target.value)}
                                                            placeholder="Descripción del servicio..." className="h-8 min-w-[180px] rounded-lg text-xs" />
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <select value={item.unidad} onChange={(e) => updateItem(i, 'unidad', e.target.value)}
                                                            className="h-8 rounded-lg border border-border bg-background px-2 text-xs outline-none">
                                                            {UNIDADES.map((u) => <option key={u.code} value={u.code}>{u.label}</option>)}
                                                        </select>
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <Input type="number" min={0.001} step="0.001" value={item.cantidad}
                                                            onChange={(e) => updateItem(i, 'cantidad', parseFloat(e.target.value) || 0)}
                                                            className="h-8 w-16 rounded-lg text-right text-xs" />
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <Input type="number" min={0} step="0.01" value={item.precio_unitario || ''}
                                                            onChange={(e) => updateItem(i, 'precio_unitario', parseFloat(e.target.value) || 0)}
                                                            className="h-8 w-24 rounded-lg text-right text-xs" />
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <Input type="number" min={0} max={100} step="0.01" value={item.descuento_pct || ''}
                                                            onChange={(e) => updateItem(i, 'descuento_pct', parseFloat(e.target.value) || 0)}
                                                            className="h-8 w-14 rounded-lg text-right text-xs" />
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <select value={item.tip_afe_igv} onChange={(e) => updateItem(i, 'tip_afe_igv', e.target.value)}
                                                            className="h-8 rounded-lg border border-border bg-background px-2 text-xs outline-none">
                                                            {TIPOS_IGV.map((t) => <option key={t.code} value={t.code}>{t.label}</option>)}
                                                        </select>
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right text-xs font-semibold tabular-nums">{fmt(total)}</td>
                                                    <td className="px-2 py-2.5">
                                                        {items.length > 1 && (
                                                            <button type="button" onClick={() => setItems((p) => p.filter((_, j) => j !== i))}
                                                                className="rounded p-1 text-muted-foreground hover:text-destructive">
                                                                <Trash2 className="size-3.5" />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            <div className="border-t border-border/60 px-5 py-4">
                                <div className="ml-auto max-w-xs space-y-1.5">
                                    {totals.gravadas > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">Op. Gravadas</span><span>{simbolo} {fmt(totals.gravadas)}</span></div>}
                                    {totals.exoneradas > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">Exoneradas</span><span>{simbolo} {fmt(totals.exoneradas)}</span></div>}
                                    {totals.igvTotal > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">I.G.V. 18%</span><span>{simbolo} {fmt(totals.igvTotal)}</span></div>}
                                    <div className="flex justify-between border-t border-border/60 pt-2 text-sm font-semibold">
                                        <span>Total</span><span>{simbolo} {fmt(totals.total)}</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {/* ── 4. OBSERVACIÓN ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold">Observaciones / Condiciones</span>
                            </div>
                            <div className="p-5">
                                <textarea
                                    value={observacion}
                                    onChange={(e) => setObservacion(e.target.value)}
                                    rows={3}
                                    placeholder="Condiciones de la propuesta, forma de pago, tiempo de entrega, notas adicionales..."
                                    className="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary/20 resize-none"
                                />
                            </div>
                        </section>

                        <div className="flex flex-wrap gap-3 pb-4">
                            <Button type="button" onClick={submit} disabled={submitting} className="gap-2 rounded-xl px-5">
                                {submitting && <Loader2 className="size-4 animate-spin" />}
                                Guardar Cotización
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => router.visit('/sunat/cotizaciones')} disabled={submitting} className="rounded-xl">
                                Cancelar
                            </Button>
                        </div>
                    </div>

                    {/* ══ VISTA PREVIA ══ */}
                    <div className="xl:sticky xl:top-20 xl:h-fit">
                        <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-soft">
                            <div className="bg-accent/60 px-5 py-3">
                                <p className="text-[10px] font-bold uppercase tracking-widest text-foreground/60">Vista previa</p>
                                <p className="text-sm font-semibold text-foreground">COTIZACIÓN / PROPUESTA</p>
                            </div>
                            <div className="p-4 font-mono text-xs">
                                <div className="mb-3 text-center">
                                    <p className="text-[11px] font-bold uppercase">{tenant.razon_social}</p>
                                    <p className="text-muted-foreground">RUC {tenant.ruc}</p>
                                    <p className="mt-1 text-[11px] font-bold text-primary">COTIZACIÓN</p>
                                    <p className="text-muted-foreground text-[10px]">N° auto-generado al guardar</p>
                                </div>

                                <div className="mb-3 space-y-1 border-t border-dashed border-border/60 pt-3">
                                    <div className="flex justify-between"><span className="text-muted-foreground">Fecha</span><span>{fmtDate(fecha)}</span></div>
                                    {fechaVenc && <div className="flex justify-between text-amber-600"><span>Válida hasta</span><span className="font-semibold">{fmtDate(fechaVenc)}</span></div>}
                                    <div className="flex justify-between"><span className="text-muted-foreground">Moneda</span><span>{moneda}</span></div>
                                </div>

                                {(clienteNombre || clienteNumDoc) && (
                                    <div className="mb-3 rounded-lg bg-muted/40 p-2.5 space-y-0.5">
                                        <p className="text-[10px] font-bold uppercase text-muted-foreground">Cliente</p>
                                        {clienteNombre && <p className="font-semibold uppercase">{clienteNombre}</p>}
                                        {clienteNumDoc && <p className="text-muted-foreground">{clienteTipoDoc === '6' ? 'RUC' : 'DNI'}: {clienteNumDoc}</p>}
                                    </div>
                                )}

                                {items.some((it) => it.descripcion) && (
                                    <div className="mb-3">
                                        <p className="mb-1.5 text-[10px] font-bold uppercase text-muted-foreground">Detalle</p>
                                        {items.filter((it) => it.descripcion).map((it, i) => {
                                            const { total } = calcItem(it);
                                            return (
                                                <div key={i} className="border-b border-border/40 pb-1.5 last:border-0">
                                                    <p className="font-medium leading-tight">{it.descripcion}</p>
                                                    <div className="flex justify-between text-[10px] text-muted-foreground">
                                                        <span>{it.cantidad} {it.unidad} × {simbolo} {fmt(it.precio_unitario)}</span>
                                                        <span className="font-semibold text-foreground">{simbolo} {fmt(total)}</span>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}

                                <div className="mb-3 space-y-1 border-t border-dashed border-border/60 pt-3">
                                    {totals.gravadas > 0 && <div className="flex justify-between"><span className="text-muted-foreground">Gravadas</span><span>{simbolo} {fmt(totals.gravadas)}</span></div>}
                                    {totals.igvTotal > 0 && <div className="flex justify-between"><span className="text-muted-foreground">I.G.V.</span><span>{simbolo} {fmt(totals.igvTotal)}</span></div>}
                                    <div className="flex justify-between border-t border-border/60 pt-1 font-bold">
                                        <span>TOTAL</span><span>{simbolo} {fmt(totals.total)}</span>
                                    </div>
                                </div>

                                {observacion && (
                                    <div className="rounded-lg border border-border/60 bg-muted/30 p-2.5">
                                        <p className="text-[10px] font-bold uppercase text-muted-foreground mb-1">Condiciones</p>
                                        <p className="text-[10px] leading-relaxed">{observacion}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SunatLayout>
    );
}
