import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AlertCircle, Building2, Calendar, CreditCard, Loader2, Plus, Search, Trash2, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import type { ClienteSunat, SerieSunat, TenantSunat } from '@/types';

// ─── Catálogos SUNAT ─────────────────────────────────────────────────────────

// Tabla 6 – Unidades de medida (agrupadas por tipo)
const UNIDADES_BIEN = [
    { code: 'NIU', label: 'NIU — Unidad' },
    { code: 'KGM', label: 'KGM — Kilogramo' },
    { code: 'GRM', label: 'GRM — Gramo' },
    { code: 'LTR', label: 'LTR — Litro' },
    { code: 'MTR', label: 'MTR — Metro' },
    { code: 'MTK', label: 'MTK — Metro Cuadrado' },
    { code: 'MTQ', label: 'MTQ — Metro Cúbico' },
    { code: 'TNE', label: 'TNE — Tonelada' },
    { code: 'GLI', label: 'GLI — Galón' },
    { code: 'PK',  label: 'PK  — Paquete' },
    { code: 'BX',  label: 'BX  — Caja' },
    { code: 'SET', label: 'SET — Juego/Conjunto' },
    { code: 'KT',  label: 'KT  — Kit' },
];

const UNIDADES_SERVICIO = [
    { code: 'ZZ',  label: 'ZZ  — Servicio' },
    { code: 'HUR', label: 'HUR — Hora' },
    { code: 'DAY', label: 'DAY — Día' },
    { code: 'MON', label: 'MON — Mes' },
    { code: 'C62', label: 'C62 — Sin unidad específica' },
];

// Tabla 7 – Tipo afectación IGV
const TIPOS_IGV = [
    { code: '10', label: 'Gravado — IGV 18%' },
    { code: '20', label: 'Exonerado' },
    { code: '30', label: 'Inafecto' },
    { code: '40', label: 'Exportación' },
];

// Tabla 17 – Tipo de operación
const TIPOS_OPERACION = [
    { code: '0101', label: 'Venta interna' },
    { code: '0112', label: 'Venta interna – sustituye guía' },
    { code: '0200', label: 'Exportación de bienes' },
    { code: '0201', label: 'Exportación de servicios' },
    { code: '1001', label: 'Sujeto a detracción' },
];

// Tabla Catálogo 54 – Bienes y servicios sujetos a detracción (SUNAT vigente)
const DETRACCIONES = [
    { codigo: '001', descripcion: 'Azúcar y melaza de caña',                    porcentaje: 10 },
    { codigo: '003', descripcion: 'Alcohol etílico',                             porcentaje: 10 },
    { codigo: '004', descripcion: 'Recursos hidrobiológicos',                    porcentaje:  4 },
    { codigo: '005', descripcion: 'Maíz amarillo duro',                          porcentaje:  4 },
    { codigo: '007', descripcion: 'Caña de azúcar',                              porcentaje: 10 },
    { codigo: '008', descripcion: 'Madera',                                      porcentaje:  4 },
    { codigo: '009', descripcion: 'Arena y piedra',                              porcentaje: 10 },
    { codigo: '010', descripcion: 'Residuos, subproductos, desechos',            porcentaje: 15 },
    { codigo: '012', descripcion: 'Intermediación laboral y tercerización',      porcentaje: 10 },
    { codigo: '014', descripcion: 'Carnes y despojos comestibles',               porcentaje:  4 },
    { codigo: '017', descripcion: 'Harina, polvo y pellets de pescado',          porcentaje:  4 },
    { codigo: '019', descripcion: 'Arrendamiento de bienes muebles',             porcentaje: 10 },
    { codigo: '020', descripcion: 'Mantenimiento y reparación de bienes muebles',porcentaje: 10 },
    { codigo: '021', descripcion: 'Movimiento de carga',                         porcentaje: 10 },
    { codigo: '022', descripcion: 'Otros servicios empresariales',               porcentaje: 10 },
    { codigo: '023', descripcion: 'Leche',                                       porcentaje:  4 },
    { codigo: '024', descripcion: 'Comisión mercantil',                          porcentaje: 10 },
    { codigo: '025', descripcion: 'Fabricación de bienes por encargo',           porcentaje: 10 },
    { codigo: '026', descripcion: 'Servicio de transporte de personas',          porcentaje: 10 },
    { codigo: '027', descripcion: 'Servicio de transporte de carga',             porcentaje:  4 },
    { codigo: '030', descripcion: 'Contratos de construcción',                   porcentaje:  4 },
    { codigo: '031', descripcion: 'Oro gravado con el IGV',                      porcentaje: 10 },
    { codigo: '034', descripcion: 'Minerales metálicos no auríferos',            porcentaje: 10 },
    { codigo: '035', descripcion: 'Bienes exonerados del IGV',                   porcentaje:  1.5 },
    { codigo: '036', descripcion: 'Oro y demás minerales metálicos exonerados',  porcentaje:  1.5 },
    { codigo: '037', descripcion: 'Demás servicios gravados con el IGV',         porcentaje: 10 },
    { codigo: '039', descripcion: 'Minerales no metálicos',                      porcentaje: 10 },
    { codigo: '040', descripcion: 'Bien inmueble gravado con IGV',               porcentaje:  4 },
];

// Catálogo 59 – Medios de pago de detracción
const MEDIOS_PAGO_DET = [
    { code: '001', label: 'Depósito en cuenta' },
    { code: '003', label: 'Transferencia de fondos' },
    { code: '004', label: 'Orden de pago' },
    { code: '005', label: 'Tarjeta de débito' },
    { code: '006', label: 'Tarjeta de crédito emitida en el país' },
    { code: '009', label: 'Efectivo — otros casos' },
];

// ─── Types ────────────────────────────────────────────────────────────────────

type TipoItem = 'bien' | 'servicio';

type ItemRow = {
    tipo_item: TipoItem;
    descripcion: string;
    unidad: string;
    cantidad: number;
    precio_unitario: number;
    descuento_pct: number;
    tip_afe_igv: string;
};

type CotizacionPrefill = {
    numero: string;
    moneda: string;
    cliente: { tipo_documento: string; numero_documento: string; razon_social: string; direccion: string };
    items: { descripcion: string; unidad: string; cantidad: number; precio_unitario: number; tip_afe_igv: string }[];
    observacion: string | null;
};

type Props = {
    tenant: TenantSunat;
    series_factura: SerieSunat[];
    series_boleta: SerieSunat[];
    clientes: ClienteSunat[];
    tipo_inicial: string;
    cotizacion?: CotizacionPrefill;
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
    return { tipo_item: 'bien', descripcion: '', unidad: 'NIU', cantidad: 1, precio_unitario: 0, descuento_pct: 0, tip_afe_igv: '10' };
}

// ─── Componente principal ─────────────────────────────────────────────────────

export default function NuevaFactura({ tenant, series_factura, series_boleta, clientes, tipo_inicial, cotizacion }: Props) {

    // ── Cabecera ──
    const [tipoDoc, setTipoDoc]   = useState<'01' | '03'>(tipo_inicial === 'boleta' ? '03' : '01');
    const series                  = tipoDoc === '01' ? series_factura : series_boleta;
    const [serie, setSerie]       = useState(series[0]?.serie ?? (tipoDoc === '01' ? 'F001' : 'B001'));
    const [fecha, setFecha]       = useState(new Date().toISOString().split('T')[0]);
    const [moneda, setMoneda]     = useState<'PEN' | 'USD'>('PEN');
    const [tipoOp, setTipoOp]     = useState('0101');

    // ── Forma de pago ──
    const [formaPago, setFormaPago]           = useState<'Contado' | 'Credito'>('Contado');
    const [fechaVencimiento, setFechaVenc]    = useState('');

    // ── Cliente (pre-fill desde cotización si aplica) ──
    const [clienteTipoDoc, setClienteTipoDoc] = useState(cotizacion?.cliente.tipo_documento ?? '6');
    const [clienteNumDoc, setClienteNumDoc]   = useState(cotizacion?.cliente.numero_documento ?? '');
    const [clienteNombre, setClienteNombre]   = useState(cotizacion?.cliente.razon_social ?? '');
    const [clienteDireccion, setClienteDireccion] = useState(cotizacion?.cliente.direccion ?? '');
    const [clienteEmail, setClienteEmail]     = useState('');
    const [clienteSearch, setClienteSearch]   = useState('');
    const [clienteResults, setClienteResults] = useState<ClienteSunat[]>([]);
    const [searchingCliente, setSearchingCliente] = useState(false);
    const [lookingUpRuc, setLookingUpRuc]     = useState(false);
    const [rucError, setRucError]             = useState('');
    const searchRef = useRef<ReturnType<typeof setTimeout>>();

    // ── Ítems (pre-fill desde cotización si aplica) ──
    const [items, setItems] = useState<ItemRow[]>(
        cotizacion?.items.length
            ? cotizacion.items.map((it) => ({
                tipo_item:       'servicio' as const,
                descripcion:     it.descripcion,
                unidad:          it.unidad,
                cantidad:        it.cantidad,
                precio_unitario: it.precio_unitario,
                descuento_pct:   0,
                tip_afe_igv:     it.tip_afe_igv,
              }))
            : [defaultItem()]
    );

    // ── Detracción ──
    const [detEnabled, setDetEnabled]     = useState(false);
    const [detCodigo, setDetCodigo]       = useState('');
    const [detCuenta, setDetCuenta]       = useState('');
    const [detMedioPago, setDetMedioPago] = useState('001');

    // ── Extras ──
    const [observacion, setObservacion] = useState('');
    const [ordenCompra, setOrdenCompra] = useState('');

    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors]         = useState<Record<string, string>>({});

    // ── Reset serie al cambiar tipo ──
    useEffect(() => {
        const s = tipoDoc === '01' ? series_factura : series_boleta;
        setSerie(s[0]?.serie ?? (tipoDoc === '01' ? 'F001' : 'B001'));
        // Al cambiar a boleta, desactivar detracción
        if (tipoDoc === '03') setDetEnabled(false);
    }, [tipoDoc]);

    // ── Auto tipo_operacion cuando se activa detracción ──
    useEffect(() => {
        if (detEnabled) setTipoOp('1001');
        else if (tipoOp === '1001') setTipoOp('0101');
    }, [detEnabled]);

    // ── Búsqueda de cliente ──
    const searchCliente = useCallback((q: string) => {
        clearTimeout(searchRef.current);
        setClienteSearch(q);
        if (q.length < 2) { setClienteResults([]); return; }
        searchRef.current = setTimeout(async () => {
            setSearchingCliente(true);
            try {
                const res = await fetch(`/sunat/buscar-cliente?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });
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
        setRucError('');
    }

    async function lookupRuc(numero: string) {
        const trimmed = numero.trim();
        if (trimmed.length !== 11 && trimmed.length !== 8) return;
        setLookingUpRuc(true);
        setRucError('');
        try {
            const res = await fetch(`/sunat/buscar-ruc?numero=${encodeURIComponent(trimmed)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                setRucError(res.status === 404 ? 'No encontrado en SUNAT.' : 'Error al consultar SUNAT.');
                return;
            }
            const data = await res.json();
            if (data.razon_social) setClienteNombre(data.razon_social);
            if (data.direccion)    setClienteDireccion(data.direccion);
            if (data.tipo_documento || trimmed.length === 11) setClienteTipoDoc(trimmed.length === 11 ? '6' : '1');
        } catch {
            setRucError('No se pudo conectar al servicio de consulta.');
        } finally {
            setLookingUpRuc(false);
        }
    }

    // ── Ítems ──
    function updateItem<K extends keyof ItemRow>(i: number, field: K, value: ItemRow[K]) {
        setItems((prev) => prev.map((it, idx) => {
            if (idx !== i) return it;
            const updated = { ...it, [field]: value };
            // Si cambia tipo_item, reset unidad al defecto del tipo
            if (field === 'tipo_item') {
                updated.unidad = value === 'servicio' ? 'ZZ' : 'NIU';
            }
            return updated;
        }));
    }
    function addItem()          { setItems((prev) => [...prev, defaultItem()]); }
    function removeItem(i: number) { setItems((prev) => prev.filter((_, idx) => idx !== i)); }

    // ── Detracción ──
    const detData = DETRACCIONES.find((d) => d.codigo === detCodigo);
    const detPct  = detData?.porcentaje ?? 0;

    // ── Totales ──
    const totals = useMemo(() => {
        let gravadas = 0, exoneradas = 0, inafectas = 0, exportacion = 0, igvTotal = 0;
        for (const it of items) {
            const { base, igv } = calcItem(it);
            if      (it.tip_afe_igv === '10') { gravadas += base; igvTotal += igv; }
            else if (it.tip_afe_igv === '20') exoneradas += base;
            else if (it.tip_afe_igv === '30') inafectas  += base;
            else if (it.tip_afe_igv === '40') exportacion += base;
        }
        const valorVenta       = gravadas + exoneradas + inafectas + exportacion;
        const totalComprobante = valorVenta + igvTotal;
        const detMonto         = detEnabled && detPct > 0 ? totalComprobante * (detPct / 100) : 0;
        const netoPagar        = totalComprobante - detMonto;
        return { gravadas, exoneradas, inafectas, exportacion, igvTotal, valorVenta, totalComprobante, detMonto, netoPagar };
    }, [items, detEnabled, detPct]);

    // ── Validación ──
    function validate() {
        const e: Record<string, string> = {};
        if (!clienteNumDoc)  e.cliente = 'Número de documento requerido.';
        if (!clienteNombre)  e.cliente_nombre = 'Razón social requerida.';
        if (items.some((it) => !it.descripcion)) e.items = 'Todos los ítems deben tener descripción.';
        if (items.some((it) => it.precio_unitario <= 0)) e.items_precio = 'El precio unitario debe ser mayor a 0.';
        if (formaPago === 'Credito' && !fechaVencimiento) e.vencimiento = 'Ingresa la fecha de vencimiento.';
        if (detEnabled && !detCodigo)  e.det_codigo  = 'Selecciona el bien o servicio sujeto a detracción.';
        if (detEnabled && !detCuenta)  e.det_cuenta  = 'La cuenta del Banco de la Nación es requerida.';
        setErrors(e);
        return Object.keys(e).length === 0;
    }

    // ── Envío ──
    function submit(enviarAuto: boolean) {
        if (!validate()) return;
        setSubmitting(true);

        const payload = {
            tipo_documento:  tipoDoc,
            serie,
            fecha_emision:   fecha,
            tipo_moneda:     moneda,
            tipo_operacion:  tipoOp,
            forma_pago:      formaPago,
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
            fecha_vencimiento: formaPago === 'Credito' ? fechaVencimiento : null,
            cuotas: formaPago === 'Credito' ? [{
                numero:      1,
                fecha_pago:  fechaVencimiento,
                monto:       totals.netoPagar,
            }] : null,
            detraccion: detEnabled && detData ? {
                cod_bien:      detData.codigo,
                porcentaje:    detData.porcentaje,
                monto:         totals.detMonto,
                cta_banco:     detCuenta,
                cod_medio_pago: detMedioPago,
            } : null,
            observacion:       observacion || null,
            extras:            ordenCompra ? { orden_compra: ordenCompra } : null,
            enviar_automatico: enviarAuto,
        };

        router.post('/sunat/facturas', payload, {
            onFinish: () => setSubmitting(false),
        });
    }

    const tipoLabel  = tipoDoc === '01' ? 'Factura' : 'Boleta';
    const simbolo    = moneda === 'PEN' ? 'S/' : '$';

    // ─── Render ───────────────────────────────────────────────────────────────
    return (
        <SunatLayout>
            <Head title={`Nueva ${tipoLabel}`} />

            <div className="mx-auto max-w-[1400px]">

                {/* Aviso cotización convertida */}
                {cotizacion && (
                    <div className="mb-4 flex items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm">
                        <span className="text-primary">✓</span>
                        <span>Datos pre-cargados desde cotización <strong>{cotizacion.numero}</strong>. Revisa y emite cuando estés listo.</span>
                    </div>
                )}

                {/* Page title */}
                <div className="mb-5 flex items-center gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Nueva {tipoLabel}
                    </h1>
                    {tenant.environment !== 'produccion' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                            <AlertCircle className="size-3" />
                            Ambiente Beta
                        </span>
                    )}
                </div>

                {/* Layout: form + preview */}
                <div className="grid gap-6 xl:grid-cols-[1fr_400px]">

                    {/* ══════════════ COLUMNA FORMULARIO ══════════════ */}
                    <div className="flex flex-col gap-5">

                        {/* ── 1. ENCABEZADO ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="flex items-center gap-2 border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold text-foreground">Datos del comprobante</span>
                            </div>
                            <div className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">

                                {/* Tipo */}
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Tipo</Label>
                                    <div className="grid grid-cols-2 gap-1.5">
                                        {[{ v: '01', l: 'Factura' }, { v: '03', l: 'Boleta' }].map(({ v, l }) => (
                                            <button
                                                key={v} type="button"
                                                onClick={() => setTipoDoc(v as '01' | '03')}
                                                className={`rounded-xl border px-3 py-2 text-sm font-medium transition-all ${
                                                    tipoDoc === v
                                                        ? 'border-primary bg-primary/10 text-primary'
                                                        : 'border-border hover:bg-secondary'
                                                }`}
                                            >{l}</button>
                                        ))}
                                    </div>
                                </div>

                                {/* Serie */}
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Serie</Label>
                                    <select
                                        value={serie}
                                        onChange={(e) => setSerie(e.target.value)}
                                        className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        {series.map((s) => (
                                            <option key={s.id} value={s.serie}>
                                                {s.serie} — Corr. {s.correlativo + 1}
                                            </option>
                                        ))}
                                        {series.length === 0 && (
                                            <option value={tipoDoc === '01' ? 'F001' : 'B001'}>
                                                {tipoDoc === '01' ? 'F001' : 'B001'}
                                            </option>
                                        )}
                                    </select>
                                </div>

                                {/* Fecha */}
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Fecha de emisión</Label>
                                    <Input
                                        type="date" value={fecha}
                                        onChange={(e) => setFecha(e.target.value)}
                                        className="h-10 rounded-xl"
                                    />
                                </div>

                                {/* Moneda */}
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Moneda</Label>
                                    <select
                                        value={moneda}
                                        onChange={(e) => setMoneda(e.target.value as 'PEN' | 'USD')}
                                        className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        <option value="PEN">PEN — Soles</option>
                                        <option value="USD">USD — Dólares</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        {/* ── 2. CONDICIONES DE PAGO ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="flex items-center gap-2 border-b border-border/60 px-5 py-3.5">
                                <CreditCard className="size-4 text-muted-foreground" />
                                <span className="text-sm font-semibold text-foreground">Condiciones de pago y operación</span>
                            </div>
                            <div className="grid gap-4 p-5 sm:grid-cols-2">

                                {/* Tipo operación */}
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Tipo de operación</Label>
                                    <select
                                        value={tipoOp}
                                        onChange={(e) => setTipoOp(e.target.value)}
                                        className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        {TIPOS_OPERACION.map((t) => (
                                            <option key={t.code} value={t.code}>{t.code} — {t.label}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Forma de pago */}
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Forma de pago</Label>
                                    <div className="grid grid-cols-2 gap-1.5">
                                        {(['Contado', 'Credito'] as const).map((fp) => (
                                            <button
                                                key={fp} type="button"
                                                onClick={() => setFormaPago(fp)}
                                                className={`rounded-xl border px-3 py-2 text-sm font-medium transition-all ${
                                                    formaPago === fp
                                                        ? 'border-primary bg-primary/10 text-primary'
                                                        : 'border-border hover:bg-secondary'
                                                }`}
                                            >
                                                {fp === 'Contado' ? 'Contado' : 'Crédito'}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {/* Fecha vencimiento — solo Crédito */}
                                {formaPago === 'Credito' && (
                                    <div className="flex flex-col gap-1.5 sm:col-span-2">
                                        <Label className="text-xs font-medium text-muted-foreground">
                                            <Calendar className="mr-1 inline size-3" />
                                            Fecha de vencimiento
                                        </Label>
                                        <Input
                                            type="date"
                                            value={fechaVencimiento}
                                            min={fecha}
                                            onChange={(e) => setFechaVenc(e.target.value)}
                                            className={`h-10 max-w-xs rounded-xl ${errors.vencimiento ? 'border-destructive' : ''}`}
                                        />
                                        {errors.vencimiento && (
                                            <p className="text-xs text-destructive">{errors.vencimiento}</p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* ── 3. CLIENTE ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="flex items-center gap-2 border-b border-border/60 px-5 py-3.5">
                                <Building2 className="size-4 text-muted-foreground" />
                                <span className="text-sm font-semibold text-foreground">Datos del adquirente / usuario</span>
                            </div>
                            <div className="p-5">
                                {/* Buscador */}
                                <div className="relative mb-4">
                                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        className="h-10 rounded-xl pl-9"
                                        placeholder="Buscar por RUC, DNI o razón social..."
                                        value={clienteSearch}
                                        onChange={(e) => searchCliente(e.target.value)}
                                    />
                                    {searchingCliente && (
                                        <Loader2 className="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                                    )}
                                    {clienteResults.length > 0 && (
                                        <div className="absolute z-50 mt-1 w-full rounded-xl border border-border bg-popover shadow-soft">
                                            {clienteResults.map((c) => (
                                                <button
                                                    key={c.id} type="button"
                                                    className="flex w-full flex-col px-4 py-2.5 text-left first:rounded-t-xl last:rounded-b-xl hover:bg-secondary"
                                                    onClick={() => selectCliente(c)}
                                                >
                                                    <span className="text-sm font-medium">{c.razon_social}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {c.tipo_documento === '6' ? 'RUC' : 'DNI'} {c.numero_documento}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">Tipo doc.</Label>
                                        <select
                                            value={clienteTipoDoc}
                                            onChange={(e) => setClienteTipoDoc(e.target.value)}
                                            className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                        >
                                            <option value="6">RUC</option>
                                            <option value="1">DNI</option>
                                            <option value="4">Carné de Extranjería</option>
                                            <option value="7">Pasaporte</option>
                                            <option value="0">Otros</option>
                                        </select>
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">
                                            Número de documento
                                        </Label>
                                        <div className="flex gap-1.5">
                                            <Input
                                                value={clienteNumDoc}
                                                onChange={(e) => {
                                                    const val = e.target.value;
                                                    setClienteNumDoc(val);
                                                    setRucError('');
                                                    if (val.length === 11 || val.length === 8) {
                                                        lookupRuc(val);
                                                    }
                                                }}
                                                placeholder={clienteTipoDoc === '6' ? '20123456789' : '12345678'}
                                                className={`h-10 rounded-xl ${errors.cliente ? 'border-destructive' : ''}`}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => lookupRuc(clienteNumDoc)}
                                                disabled={lookingUpRuc || clienteNumDoc.length < 8}
                                                title="Consultar en SUNAT / RENIEC"
                                                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border bg-secondary text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                                            >
                                                {lookingUpRuc
                                                    ? <Loader2 className="size-4 animate-spin" />
                                                    : <RefreshCw className="size-4" />
                                                }
                                            </button>
                                        </div>
                                        {rucError && <p className="text-xs text-destructive">{rucError}</p>}
                                        {errors.cliente && <p className="text-xs text-destructive">{errors.cliente}</p>}
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">Razón social / Nombre</Label>
                                        <Input
                                            value={clienteNombre}
                                            onChange={(e) => setClienteNombre(e.target.value)}
                                            placeholder="EMPRESA SAC"
                                            className={`h-10 rounded-xl ${errors.cliente_nombre ? 'border-destructive' : ''}`}
                                        />
                                        {errors.cliente_nombre && <p className="text-xs text-destructive">{errors.cliente_nombre}</p>}
                                    </div>
                                    <div className="flex flex-col gap-1.5 sm:col-span-2">
                                        <Label className="text-xs font-medium text-muted-foreground">Dirección</Label>
                                        <Input
                                            value={clienteDireccion}
                                            onChange={(e) => setClienteDireccion(e.target.value)}
                                            placeholder="Av. Los Olivos 123, Lima"
                                            className="h-10 rounded-xl"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <Label className="text-xs font-medium text-muted-foreground">Email (PDF)</Label>
                                        <Input
                                            type="email"
                                            value={clienteEmail}
                                            onChange={(e) => setClienteEmail(e.target.value)}
                                            placeholder="cliente@empresa.com"
                                            className="h-10 rounded-xl"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>

                        {/* ── 4. ÍTEMS ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="flex items-center justify-between border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold text-foreground">Descripción de bienes vendidos / servicios prestados</span>
                                <button
                                    type="button" onClick={addItem}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-border bg-secondary px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted"
                                >
                                    <Plus className="size-3.5" /> Agregar ítem
                                </button>
                            </div>
                            {(errors.items || errors.items_precio) && (
                                <div className="border-b border-destructive/20 bg-destructive/5 px-5 py-2 text-xs text-destructive">
                                    {errors.items || errors.items_precio}
                                </div>
                            )}
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border/60 bg-muted/30">
                                        <tr>
                                            <th className="px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Tipo</th>
                                            <th className="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Descripción</th>
                                            <th className="px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">U. Med.</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Cant.</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">P. Unit.</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Desc.%</th>
                                            <th className="px-3 py-3 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">IGV</th>
                                            <th className="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Total</th>
                                            <th className="px-2 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item, i) => {
                                            const { total } = calcItem(item);
                                            const unidades  = item.tipo_item === 'servicio' ? UNIDADES_SERVICIO : UNIDADES_BIEN;
                                            return (
                                                <tr key={i} className="border-b border-border/40 last:border-0 hover:bg-muted/20">
                                                    {/* Tipo bien/servicio */}
                                                    <td className="px-3 py-2.5">
                                                        <div className="flex flex-col gap-1">
                                                            {(['bien', 'servicio'] as const).map((t) => (
                                                                <label key={t} className="flex cursor-pointer items-center gap-1">
                                                                    <input
                                                                        type="radio"
                                                                        name={`tipo-${i}`}
                                                                        checked={item.tipo_item === t}
                                                                        onChange={() => updateItem(i, 'tipo_item', t)}
                                                                        className="accent-primary"
                                                                    />
                                                                    <span className="text-[10px] text-muted-foreground">
                                                                        {t === 'bien' ? 'Bien' : 'Serv.'}
                                                                    </span>
                                                                </label>
                                                            ))}
                                                        </div>
                                                    </td>
                                                    {/* Descripción */}
                                                    <td className="px-3 py-2.5">
                                                        <Input
                                                            value={item.descripcion}
                                                            onChange={(e) => updateItem(i, 'descripcion', e.target.value)}
                                                            placeholder={item.tipo_item === 'servicio' ? 'Ej: Servicio de consultoría' : 'Ej: Producto XYZ'}
                                                            className="h-8 min-w-[180px] rounded-lg text-xs"
                                                        />
                                                    </td>
                                                    {/* Unidad de medida */}
                                                    <td className="px-3 py-2.5">
                                                        <select
                                                            value={item.unidad}
                                                            onChange={(e) => updateItem(i, 'unidad', e.target.value)}
                                                            className="h-8 rounded-lg border border-border bg-background px-2 text-xs outline-none"
                                                        >
                                                            {unidades.map((u) => (
                                                                <option key={u.code} value={u.code}>{u.label}</option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                    {/* Cantidad */}
                                                    <td className="px-3 py-2.5">
                                                        <Input
                                                            type="number" min={0.001} step="0.001"
                                                            value={item.cantidad}
                                                            onChange={(e) => updateItem(i, 'cantidad', parseFloat(e.target.value) || 0)}
                                                            className="h-8 w-16 rounded-lg text-right text-xs"
                                                        />
                                                    </td>
                                                    {/* Precio unitario */}
                                                    <td className="px-3 py-2.5">
                                                        <Input
                                                            type="number" min={0} step="0.01"
                                                            value={item.precio_unitario || ''}
                                                            onChange={(e) => updateItem(i, 'precio_unitario', parseFloat(e.target.value) || 0)}
                                                            className="h-8 w-24 rounded-lg text-right text-xs"
                                                        />
                                                    </td>
                                                    {/* Descuento % */}
                                                    <td className="px-3 py-2.5">
                                                        <Input
                                                            type="number" min={0} max={100} step="0.01"
                                                            value={item.descuento_pct || ''}
                                                            onChange={(e) => updateItem(i, 'descuento_pct', parseFloat(e.target.value) || 0)}
                                                            className="h-8 w-14 rounded-lg text-right text-xs"
                                                        />
                                                    </td>
                                                    {/* IGV */}
                                                    <td className="px-3 py-2.5">
                                                        <select
                                                            value={item.tip_afe_igv}
                                                            onChange={(e) => updateItem(i, 'tip_afe_igv', e.target.value)}
                                                            className="h-8 rounded-lg border border-border bg-background px-2 text-xs outline-none"
                                                        >
                                                            {TIPOS_IGV.map((t) => (
                                                                <option key={t.code} value={t.code}>{t.label}</option>
                                                            ))}
                                                        </select>
                                                    </td>
                                                    {/* Total */}
                                                    <td className="px-3 py-2.5 text-right text-xs font-semibold tabular-nums">
                                                        {fmt(total)}
                                                    </td>
                                                    {/* Eliminar */}
                                                    <td className="px-2 py-2.5">
                                                        {items.length > 1 && (
                                                            <button
                                                                type="button" onClick={() => removeItem(i)}
                                                                className="rounded p-1 text-muted-foreground transition-colors hover:text-destructive"
                                                            >
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

                            {/* Subtotales en el pie de la tabla */}
                            <div className="border-t border-border/60 px-5 py-4">
                                <div className="ml-auto max-w-xs space-y-1.5">
                                    {totals.gravadas > 0 && (
                                        <div className="flex justify-between text-xs">
                                            <span className="text-muted-foreground">Op. Gravadas</span>
                                            <span className="tabular-nums">{simbolo} {fmt(totals.gravadas)}</span>
                                        </div>
                                    )}
                                    {totals.exoneradas > 0 && (
                                        <div className="flex justify-between text-xs">
                                            <span className="text-muted-foreground">Op. Exoneradas</span>
                                            <span className="tabular-nums">{simbolo} {fmt(totals.exoneradas)}</span>
                                        </div>
                                    )}
                                    {totals.inafectas > 0 && (
                                        <div className="flex justify-between text-xs">
                                            <span className="text-muted-foreground">Op. Inafectas</span>
                                            <span className="tabular-nums">{simbolo} {fmt(totals.inafectas)}</span>
                                        </div>
                                    )}
                                    {totals.igvTotal > 0 && (
                                        <div className="flex justify-between text-xs">
                                            <span className="text-muted-foreground">I.G.V. (18%)</span>
                                            <span className="tabular-nums">{simbolo} {fmt(totals.igvTotal)}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between border-t border-border/60 pt-2 text-sm font-semibold">
                                        <span>Precio de venta</span>
                                        <span className="tabular-nums">{simbolo} {fmt(totals.totalComprobante)}</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {/* ── 5. DETRACCIÓN (solo Factura) ── */}
                        {tipoDoc === '01' && (
                            <section className="rounded-2xl border border-border bg-card shadow-soft">
                                <div className="flex items-center justify-between border-b border-border/60 px-5 py-3.5">
                                    <span className="text-sm font-semibold text-foreground">Detracción (SPOT)</span>
                                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={detEnabled}
                                            onChange={(e) => setDetEnabled(e.target.checked)}
                                            className="size-4 accent-primary rounded"
                                        />
                                        <span className="text-xs text-muted-foreground">Aplicar detracción</span>
                                    </label>
                                </div>

                                {detEnabled && (
                                    <div className="p-5">
                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {/* Bien/servicio Catálogo 54 */}
                                            <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-3">
                                                <Label className="text-xs font-medium text-muted-foreground">
                                                    Bien o servicio sujeto a detracción (Catálogo 54)
                                                </Label>
                                                <select
                                                    value={detCodigo}
                                                    onChange={(e) => setDetCodigo(e.target.value)}
                                                    className={`h-10 rounded-xl border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20 ${
                                                        errors.det_codigo ? 'border-destructive' : 'border-border'
                                                    }`}
                                                >
                                                    <option value="">— Selecciona el bien o servicio —</option>
                                                    {DETRACCIONES.map((d) => (
                                                        <option key={d.codigo} value={d.codigo}>
                                                            {d.codigo} — {d.descripcion} ({d.porcentaje}%)
                                                        </option>
                                                    ))}
                                                </select>
                                                {errors.det_codigo && <p className="text-xs text-destructive">{errors.det_codigo}</p>}
                                            </div>

                                            {/* Cuenta Banco de la Nación */}
                                            <div className="flex flex-col gap-1.5">
                                                <Label className="text-xs font-medium text-muted-foreground">
                                                    Cuenta Banco de la Nación <span className="text-destructive">*</span>
                                                </Label>
                                                <Input
                                                    value={detCuenta}
                                                    onChange={(e) => setDetCuenta(e.target.value)}
                                                    placeholder="00-000-000000"
                                                    className={`h-10 rounded-xl ${errors.det_cuenta ? 'border-destructive' : ''}`}
                                                />
                                                {errors.det_cuenta && <p className="text-xs text-destructive">{errors.det_cuenta}</p>}
                                            </div>

                                            {/* Medio de pago Catálogo 59 */}
                                            <div className="flex flex-col gap-1.5">
                                                <Label className="text-xs font-medium text-muted-foreground">
                                                    Medio de pago (Catálogo 59)
                                                </Label>
                                                <select
                                                    value={detMedioPago}
                                                    onChange={(e) => setDetMedioPago(e.target.value)}
                                                    className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                                >
                                                    {MEDIOS_PAGO_DET.map((m) => (
                                                        <option key={m.code} value={m.code}>
                                                            {m.code} — {m.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>

                                        {/* Info box detracción */}
                                        {detCodigo && (
                                            <div className="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
                                                <p className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-primary">
                                                    Datos de detracción calculados
                                                </p>
                                                <div className="grid gap-2 sm:grid-cols-3">
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Bien/Servicio</p>
                                                        <p className="text-xs font-medium">{detData?.codigo} — {detData?.descripcion}</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Porcentaje</p>
                                                        <p className="text-xs font-medium">{detPct}%</p>
                                                    </div>
                                                    <div>
                                                        <p className="text-[10px] text-muted-foreground">Monto a detraer</p>
                                                        <p className="text-sm font-bold text-primary">
                                                            {simbolo} {fmt(totals.detMonto)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </section>
                        )}

                        {/* ── 6. DATOS ADICIONALES ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold text-foreground">Datos adicionales</span>
                            </div>
                            <div className="grid gap-4 p-5 sm:grid-cols-2">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Orden de compra referencial</Label>
                                    <Input
                                        value={ordenCompra}
                                        onChange={(e) => setOrdenCompra(e.target.value)}
                                        placeholder="OC-001"
                                        className="h-10 rounded-xl"
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Observación</Label>
                                    <Input
                                        value={observacion}
                                        onChange={(e) => setObservacion(e.target.value)}
                                        placeholder="Comentario o nota interna"
                                        className="h-10 rounded-xl"
                                    />
                                </div>
                            </div>
                        </section>

                        {/* ── ACCIONES ── */}
                        <div className="flex flex-wrap items-center gap-3 pb-4">
                            <Button
                                type="button" onClick={() => submit(true)} disabled={submitting}
                                className="gap-2 rounded-xl px-5"
                            >
                                {submitting && <Loader2 className="size-4 animate-spin" />}
                                Emitir y enviar a SUNAT
                            </Button>
                            <Button
                                type="button" variant="outline" onClick={() => submit(false)} disabled={submitting}
                                className="rounded-xl"
                            >
                                Guardar borrador
                            </Button>
                            <Button
                                type="button" variant="ghost" onClick={() => router.visit('/sunat')} disabled={submitting}
                                className="rounded-xl"
                            >
                                Cancelar
                            </Button>
                        </div>
                    </div>

                    {/* ══════════════ COLUMNA VISTA PREVIA ══════════════ */}
                    <div className="xl:sticky xl:top-20 xl:h-fit">
                        <div className="rounded-2xl border border-border bg-card shadow-soft overflow-hidden">

                            {/* Cabecera vista previa */}
                            <div className="bg-primary px-5 py-3">
                                <p className="text-[10px] font-bold uppercase tracking-widest text-primary-foreground/80">
                                    Vista previa
                                </p>
                                <p className="text-sm font-semibold text-primary-foreground">
                                    {tipoDoc === '01' ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA'}
                                </p>
                            </div>

                            <div className="p-4 font-mono text-xs">

                                {/* Emisor */}
                                <div className="mb-3 text-center">
                                    <p className="text-[11px] font-bold uppercase tracking-tight">
                                        {tenant.razon_social}
                                    </p>
                                    <p className="text-muted-foreground">RUC {tenant.ruc}</p>
                                    <p className="mt-1 text-[11px] font-bold text-primary">
                                        {tipoDoc === '01' ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA'}
                                    </p>
                                    <p className="font-semibold">{serie}-00000001</p>
                                </div>

                                <div className="mb-3 border-t border-dashed border-border/60 pt-3 space-y-1">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Fecha emisión</span>
                                        <span>{fmtDate(fecha)}</span>
                                    </div>
                                    {formaPago === 'Credito' && fechaVencimiento && (
                                        <div className="flex justify-between text-orange-600 dark:text-orange-400">
                                            <span>Fecha vencimiento</span>
                                            <span className="font-semibold">{fmtDate(fechaVencimiento)}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Moneda</span>
                                        <span>{moneda}</span>
                                    </div>
                                </div>

                                {/* Adquirente */}
                                {(clienteNombre || clienteNumDoc) && (
                                    <div className="mb-3 rounded-lg bg-muted/40 p-2.5 space-y-0.5">
                                        <p className="text-[10px] font-bold uppercase text-muted-foreground">Adquirente / usuario</p>
                                        {clienteNombre && <p className="font-semibold uppercase">{clienteNombre}</p>}
                                        {clienteNumDoc && (
                                            <p className="text-muted-foreground">
                                                {clienteTipoDoc === '6' ? 'RUC' : 'DNI'}: {clienteNumDoc}
                                            </p>
                                        )}
                                        {clienteDireccion && <p className="text-muted-foreground">{clienteDireccion}</p>}
                                    </div>
                                )}

                                {/* Items */}
                                {items.some((it) => it.descripcion) && (
                                    <div className="mb-3">
                                        <p className="mb-1.5 text-[10px] font-bold uppercase text-muted-foreground">Detalle</p>
                                        <div className="space-y-1.5">
                                            {items.filter((it) => it.descripcion).map((it, i) => {
                                                const { total } = calcItem(it);
                                                return (
                                                    <div key={i} className="border-b border-border/40 pb-1.5 last:border-0">
                                                        <p className="font-medium uppercase leading-tight">{it.descripcion}</p>
                                                        <div className="flex justify-between text-[10px] text-muted-foreground">
                                                            <span>{it.cantidad} {it.unidad} × {simbolo} {fmt(it.precio_unitario)}</span>
                                                            <span className="font-semibold text-foreground">{simbolo} {fmt(total)}</span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {/* Totales */}
                                <div className="mb-3 border-t border-dashed border-border/60 pt-3 space-y-1">
                                    {totals.gravadas > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Op. Gravadas</span>
                                            <span>{simbolo} {fmt(totals.gravadas)}</span>
                                        </div>
                                    )}
                                    {totals.exoneradas > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Exoneradas</span>
                                            <span>{simbolo} {fmt(totals.exoneradas)}</span>
                                        </div>
                                    )}
                                    {totals.igvTotal > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">I.G.V. 18%</span>
                                            <span>{simbolo} {fmt(totals.igvTotal)}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between border-t border-border/60 pt-1 font-bold">
                                        <span>PRECIO DE VENTA</span>
                                        <span>{simbolo} {fmt(totals.totalComprobante)}</span>
                                    </div>
                                </div>

                                {/* Cuadro detracción */}
                                {detEnabled && detCodigo && totals.detMonto > 0 && (
                                    <div className="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-2.5 dark:border-amber-700/50 dark:bg-amber-900/20">
                                        <p className="mb-1.5 text-[10px] font-bold uppercase text-amber-800 dark:text-amber-300">
                                            Datos de detracción
                                        </p>
                                        <div className="space-y-0.5 text-[11px]">
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Bien/Serv.</span>
                                                <span className="text-right">{detData?.codigo} — {detData?.descripcion?.slice(0, 28)}{(detData?.descripcion?.length ?? 0) > 28 ? '...' : ''}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Porcentaje</span>
                                                <span>{detPct}%</span>
                                            </div>
                                            <div className="flex justify-between font-semibold text-amber-800 dark:text-amber-300">
                                                <span>Monto detracción</span>
                                                <span>{simbolo} {fmt(totals.detMonto)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Medio de pago</span>
                                                <span>{MEDIOS_PAGO_DET.find(m => m.code === detMedioPago)?.label ?? detMedioPago}</span>
                                            </div>
                                            {detCuenta && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Cta. Banco Nación</span>
                                                    <span>{detCuenta}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Cuadro crédito */}
                                {formaPago === 'Credito' && (
                                    <div className="mb-3 rounded-lg border border-blue-300 bg-blue-50 p-2.5 dark:border-blue-700/50 dark:bg-blue-900/20">
                                        <p className="mb-1.5 text-[10px] font-bold uppercase text-blue-800 dark:text-blue-300">
                                            Condición de pago: Crédito
                                        </p>
                                        <div className="space-y-0.5 text-[11px]">
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Fecha vencimiento</span>
                                                <span className="font-semibold">
                                                    {fechaVencimiento ? fmtDate(fechaVencimiento) : '—'}
                                                </span>
                                            </div>
                                            <div className="flex justify-between font-semibold text-blue-800 dark:text-blue-300">
                                                <span>Monto a cobrar</span>
                                                <span>{simbolo} {fmt(totals.netoPagar)}</span>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Importe neto a pagar */}
                                <div className="rounded-xl bg-primary px-4 py-3 text-center">
                                    <p className="text-[10px] font-bold uppercase tracking-wide text-primary-foreground/80">
                                        {detEnabled && totals.detMonto > 0
                                            ? 'Importe neto a pagar'
                                            : 'Importe total a pagar'}
                                    </p>
                                    <p className="text-xl font-bold text-primary-foreground tabular-nums">
                                        {simbolo} {fmt(totals.netoPagar)}
                                    </p>
                                    {detEnabled && totals.detMonto > 0 && (
                                        <p className="mt-0.5 text-[10px] text-primary-foreground/70">
                                            (Precio de venta {simbolo} {fmt(totals.totalComprobante)} — Detracción {simbolo} {fmt(totals.detMonto)})
                                        </p>
                                    )}
                                </div>

                                {/* QR placeholder */}
                                <div className="mt-3 flex items-center justify-center gap-3 border-t border-dashed border-border/60 pt-3">
                                    <div className="flex size-14 items-center justify-center rounded border-2 border-border bg-muted/40">
                                        <span className="text-[8px] text-muted-foreground text-center leading-tight">QR<br/>SUNAT</span>
                                    </div>
                                    <div className="flex-1 text-[9px] text-muted-foreground leading-relaxed">
                                        <p>Representación impresa de comprobante electrónico</p>
                                        <p className="mt-1">Consulte su comprobante en:</p>
                                        <p className="font-medium text-foreground">www.sunat.gob.pe</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </SunatLayout>
    );
}
