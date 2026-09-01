import { Head, router, usePage } from '@inertiajs/react';
import { Loader2, Plus, Trash2, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ClienteSelector, type ClienteData } from '@/components/sunat/cliente-selector';
import { PdfPreviewModal } from '@/components/sunat/pdf-preview-modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import type { SerieSunat, SharedData, TenantSunat } from '@/types';

type Emitido = { tipo: string; id: number; numero: string; formato?: string };

const PDF_FORMATOS = [{ v: 'a4', l: 'A4' }, { v: 'a5', l: 'A5' }, { v: 'ticket-80', l: '80mm' }, { v: 'ticket-58', l: '58mm' }];

const MOTIVOS = [
    { code: '01', label: 'Venta' },
    { code: '02', label: 'Compra' },
    { code: '04', label: 'Traslado entre establecimientos de la misma empresa' },
    { code: '08', label: 'Importación' },
    { code: '09', label: 'Exportación' },
    { code: '13', label: 'Otros' },
    { code: '14', label: 'Venta sujeta a confirmación del comprador' },
    { code: '18', label: 'Traslado emisor itinerante de comprobantes' },
    { code: '19', label: 'Traslado a zona primaria' },
];

const DOC_REL = [
    { code: '01', label: 'Factura' },
    { code: '03', label: 'Boleta' },
    { code: '09', label: 'Guía de remisión remitente' },
    { code: '50', label: 'Declaración Aduanera (DAM)' },
    { code: '52', label: 'Declaración Simplificada (DS)' },
];

const UNIDADES_GUIA = [
    { code: 'NIU', label: 'NIU - Unidad' },
    { code: 'KGM', label: 'KGM - Kilogramo' },
    { code: 'GRM', label: 'GRM - Gramo' },
    { code: 'TNE', label: 'TNE - Tonelada' },
    { code: 'LTR', label: 'LTR - Litro' },
    { code: 'MTR', label: 'MTR - Metro' },
    { code: 'BX', label: 'BX - Caja' },
    { code: 'PK', label: 'PK - Paquete' },
    { code: 'SET', label: 'SET - Juego' },
    { code: 'GLI', label: 'GLI - Galón' },
];

type Item = { descripcion: string; cantidad: string; unidad: string };
type Doc = { tipo_doc: string; num_doc: string; razon_social: string };

type Props = {
    tenant: TenantSunat;
    series_remitente: SerieSunat[];
    series_transportista: SerieSunat[];
    clientes: unknown[];
};

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
            {children}
        </div>
    );
}

function DocFields({ value, onChange, conRuc = true }: { value: Doc; onChange: (d: Doc) => void; conRuc?: boolean }) {
    const [loading, setLoading] = useState(false);

    const lookup = async () => {
        const n = value.num_doc.trim();
        if (n.length !== 11 && n.length !== 8) return;
        setLoading(true);
        try {
            const res = await fetch(`/sunat/buscar-ruc?numero=${encodeURIComponent(n)}`, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                if (data.razon_social) onChange({ ...value, razon_social: data.razon_social, tipo_doc: n.length === 11 ? '6' : '1' });
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="grid gap-3 sm:grid-cols-3">
            <Field label="Tipo doc.">
                <select
                    value={value.tipo_doc}
                    onChange={(e) => onChange({ ...value, tipo_doc: e.target.value })}
                    className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background"
                >
                    {conRuc && <option value="6">RUC</option>}
                    <option value="1">DNI</option>
                </select>
            </Field>
            <Field label="Número">
                <Input value={value.num_doc} onChange={(e) => onChange({ ...value, num_doc: e.target.value })} onBlur={lookup} className="h-10 rounded-xl" />
            </Field>
            <Field label="Razón social / Nombre">
                <Input value={value.razon_social} onChange={(e) => onChange({ ...value, razon_social: e.target.value })} className="h-10 rounded-xl" placeholder={loading ? 'Buscando…' : ''} />
            </Field>
        </div>
    );
}

export default function NuevaGuia({ tenant, series_remitente, series_transportista }: Props) {
    const { props } = usePage<SharedData>();
    const can = (a: string) => props.empresa?.can?.includes(a) ?? false;
    const puedeRemitente = can('guia_remitente.emitir');
    const puedeTransportista = can('guia_transportista.emitir');

    const [tipo, setTipo] = useState<'09' | '31'>(puedeRemitente ? '09' : '31');
    const series = tipo === '09' ? series_remitente : series_transportista;
    const [serie, setSerie] = useState(series[0]?.serie ?? '');
    const [fechaEmision, setFechaEmision] = useState(new Date().toLocaleDateString('en-CA'));
    const [fechaTraslado, setFechaTraslado] = useState(new Date().toLocaleDateString('en-CA'));
    const [observacion, setObservacion] = useState('');

    const [codTraslado, setCodTraslado] = useState('01');
    const [modTraslado, setModTraslado] = useState<'01' | '02'>('02');
    const [peso, setPeso] = useState('1');
    const [undPeso, setUndPeso] = useState('KGM');
    const [numBultos, setNumBultos] = useState('');

    const [partidaUbigeo, setPartidaUbigeo] = useState('');
    const [partidaDir, setPartidaDir] = useState('');
    const [llegadaUbigeo, setLlegadaUbigeo] = useState('');
    const [llegadaDir, setLlegadaDir] = useState('');

    const [destinatario, setDestinatario] = useState<ClienteData | null>(null);
    const [remitente, setRemitente] = useState<ClienteData | null>(null);
    const [transportista, setTransportista] = useState<Doc>({ tipo_doc: '6', num_doc: '', razon_social: '' });
    const [transMtc, setTransMtc] = useState('');

    const [vehPlaca, setVehPlaca] = useState('');
    const [cond, setCond] = useState({ tipo_doc: '1', num_doc: '', nombres: '', apellidos: '', licencia: '' });

    const [docRel, setDocRel] = useState({ tipo_codigo: '01', numero: '', ruc_emisor: '' });

    const [items, setItems] = useState<Item[]>([{ descripcion: '', cantidad: '1', unidad: 'NIU' }]);
    const [submitting, setSubmitting] = useState(false);

    // PDF: formato + modal tras emitir
    const [pdfFormat, setPdfFormat] = useState('a4');
    const [pdfModal, setPdfModal]   = useState<Emitido | null>(null);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const emitidoFlash = (props as any)?.flash?.emitido as Emitido | undefined;
    useEffect(() => {
        if (emitidoFlash?.id) setPdfModal(emitidoFlash);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [emitidoFlash?.id]);
    const [error, setError] = useState('');

    const esGRT = tipo === '31';
    const usaVehiculo = esGRT || modTraslado === '02';
    const usaTransportista = !esGRT && modTraslado === '01';

    const cambiarTipo = (t: '09' | '31') => {
        setTipo(t);
        const s = t === '09' ? series_remitente : series_transportista;
        setSerie(s[0]?.serie ?? '');
    };

    const addItem = () => setItems((p) => [...p, { descripcion: '', cantidad: '1', unidad: 'NIU' }]);
    const removeItem = (i: number) => setItems((p) => p.filter((_, idx) => idx !== i));
    const updItem = (i: number, k: keyof Item, v: string) => setItems((p) => p.map((it, idx) => (idx === i ? { ...it, [k]: v } : it)));

    const submit = (enviar: boolean) => {
        setError('');
        if (!serie) return setError('Selecciona o escribe una serie.');
        if (!destinatario?.num_doc || !destinatario?.razon_social) return setError('Completa el destinatario.');
        if (!partidaUbigeo || !partidaDir) return setError('Completa el punto de partida.');
        if (!llegadaUbigeo || !llegadaDir) return setError('Completa el punto de llegada.');
        if (items.some((it) => !it.descripcion)) return setError('Todos los ítems necesitan descripción.');
        if (usaTransportista && (!transportista.num_doc || !transportista.razon_social)) return setError('Completa los datos del transportista.');
        if (usaVehiculo && !vehPlaca) return setError('Ingresa la placa del vehículo.');
        if (usaVehiculo && (!cond.num_doc || !cond.licencia)) return setError('Completa los datos del conductor.');
        if (esGRT && (!remitente?.num_doc || !remitente?.razon_social)) return setError('La guía transportista requiere el remitente.');
        if (esGRT && !docRel.numero) return setError('La guía transportista requiere un documento relacionado.');

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const payload: Record<string, any> = {
            tipo_documento: tipo,
            serie,
            fecha_emision: fechaEmision,
            observacion: observacion || undefined,
            destinatario: destinatario ? { tipo_doc: destinatario.tipo_doc, num_doc: destinatario.num_doc, razon_social: destinatario.razon_social } : undefined,
            cod_traslado: codTraslado,
            mod_traslado: esGRT ? '02' : modTraslado,
            fecha_traslado: fechaTraslado,
            peso_total: Number(peso),
            und_peso_total: undPeso,
            num_bultos: numBultos ? Number(numBultos) : undefined,
            partida_ubigeo: partidaUbigeo,
            partida_direccion: partidaDir,
            llegada_ubigeo: llegadaUbigeo,
            llegada_direccion: llegadaDir,
            items: items.map((it) => ({ descripcion: it.descripcion, cantidad: Number(it.cantidad), unidad: it.unidad || undefined })),
            enviar_automatico: enviar,
            pdf_format: pdfFormat,
        };

        if (usaTransportista) {
            payload.transportista = { tipo_doc: '6', num_doc: transportista.num_doc, razon_social: transportista.razon_social, nro_mtc: transMtc || undefined };
        }
        if (usaVehiculo) {
            payload.vehiculo = { placa: vehPlaca.toUpperCase() };
            payload.conductor = { ...cond };
        }
        if (esGRT) {
            payload.remitente = remitente ? { tipo_doc: remitente.tipo_doc, num_doc: remitente.num_doc, razon_social: remitente.razon_social } : undefined;
            payload.doc_relacionado = [{ tipo_codigo: docRel.tipo_codigo, numero: docRel.numero, ruc_emisor: docRel.ruc_emisor || undefined }];
        }

        setSubmitting(true);
        router.post('/sunat/guias', payload, { onFinish: () => setSubmitting(false) });
    };

    return (
        <SunatLayout>
            <Head title="Nueva guía de remisión" />

            <div className="mx-auto max-w-4xl space-y-5">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <Truck className="size-5" />
                    </span>
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold text-foreground">Nueva guía de remisión</h1>
                            {tenant.environment !== 'produccion' && (
                                <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                    Ambiente Beta
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">Traslado de bienes con soporte a SUNAT.</p>
                    </div>
                </header>

                {error && <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</div>}

                {/* Tipo + cabecera */}
                <section className="space-y-4 rounded-2xl border border-border bg-card p-5">
                    <Field label="Tipo de guía">
                        <div className="grid grid-cols-2 gap-2">
                            {puedeRemitente && (
                                <label className="flex cursor-pointer items-center gap-2 py-2 text-sm font-medium text-foreground">
                                    <input type="radio" name="tipo-guia" value="09" checked={tipo === '09'} onChange={() => cambiarTipo('09')} className="size-4 shrink-0 appearance-none rounded-full border-2 border-muted-foreground/40 bg-transparent transition-colors checked:border-primary checked:bg-primary checked:bg-clip-content checked:p-[3px]" />
                                    Remitente (09)
                                </label>
                            )}
                            {puedeTransportista && (
                                <label className="flex cursor-pointer items-center gap-2 py-2 text-sm font-medium text-foreground">
                                    <input type="radio" name="tipo-guia" value="31" checked={tipo === '31'} onChange={() => cambiarTipo('31')} className="size-4 shrink-0 appearance-none rounded-full border-2 border-muted-foreground/40 bg-transparent transition-colors checked:border-primary checked:bg-primary checked:bg-clip-content checked:p-[3px]" />
                                    Transportista (31)
                                </label>
                            )}
                        </div>
                    </Field>

                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field label="Serie">
                            <Input value={serie} onChange={(e) => setSerie(e.target.value.toUpperCase())} placeholder={tipo === '09' ? 'T001' : 'V001'} className="h-10 rounded-xl" />
                        </Field>
                        <Field label="Fecha de emisión">
                            <Input type="date" value={fechaEmision} onChange={(e) => setFechaEmision(e.target.value)} className="h-10 rounded-xl" />
                        </Field>
                        <Field label="Fecha de traslado">
                            <Input type="date" value={fechaTraslado} onChange={(e) => setFechaTraslado(e.target.value)} className="h-10 rounded-xl" />
                        </Field>
                    </div>
                </section>

                {/* Traslado */}
                <section className="space-y-4 rounded-2xl border border-border bg-card p-5">
                    <h2 className="text-sm font-semibold text-foreground">Datos del traslado</h2>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Motivo de traslado">
                            <select value={codTraslado} onChange={(e) => setCodTraslado(e.target.value)} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                                {MOTIVOS.map((m) => (
                                    <option key={m.code} value={m.code}>{m.code} - {m.label}</option>
                                ))}
                            </select>
                        </Field>
                        {!esGRT && (
                            <Field label="Modalidad de transporte">
                                <div className="grid grid-cols-2 gap-2">
                                    {(['02', '01'] as const).map((m) => (
                                        <label key={m} className="flex cursor-pointer items-center gap-2 py-2 text-sm text-foreground">
                                            <input type="radio" name="mod-traslado" value={m} checked={modTraslado === m} onChange={() => setModTraslado(m)} className="size-4 shrink-0 appearance-none rounded-full border-2 border-muted-foreground/40 bg-transparent transition-colors checked:border-primary checked:bg-primary checked:bg-clip-content checked:p-[3px]" />
                                            {m === '02' ? 'Privado' : 'Público'}
                                        </label>
                                    ))}
                                </div>
                            </Field>
                        )}
                        <Field label="Peso total">
                            <Input type="number" min={0} step="0.001" value={peso} onChange={(e) => setPeso(e.target.value)} className="h-10 rounded-xl" />
                        </Field>
                        <Field label="Unidad de peso">
                            <select value={undPeso} onChange={(e) => setUndPeso(e.target.value)} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                                <option value="KGM">KGM - Kilogramo</option>
                                <option value="TNE">TNE - Tonelada</option>
                            </select>
                        </Field>
                        <Field label="N° de bultos (opcional)">
                            <Input type="number" min={1} value={numBultos} onChange={(e) => setNumBultos(e.target.value)} className="h-10 rounded-xl" />
                        </Field>
                    </div>
                </section>

                {/* Puntos */}
                <section className="grid gap-4 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-foreground">Punto de partida</h2>
                        <Field label="Ubigeo (6 dígitos)"><Input value={partidaUbigeo} onChange={(e) => setPartidaUbigeo(e.target.value)} placeholder="150101" className="h-10 rounded-xl" /></Field>
                        <Field label="Dirección"><Input value={partidaDir} onChange={(e) => setPartidaDir(e.target.value)} className="h-10 rounded-xl" /></Field>
                    </div>
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-foreground">Punto de llegada</h2>
                        <Field label="Ubigeo (6 dígitos)"><Input value={llegadaUbigeo} onChange={(e) => setLlegadaUbigeo(e.target.value)} placeholder="150101" className="h-10 rounded-xl" /></Field>
                        <Field label="Dirección"><Input value={llegadaDir} onChange={(e) => setLlegadaDir(e.target.value)} className="h-10 rounded-xl" /></Field>
                    </div>
                </section>

                {/* Destinatario */}
                <ClienteSelector value={destinatario} onChange={setDestinatario} label="Destinatario" showDireccion={false} showEmail={false} />

                {/* Remitente (GRT) */}
                {esGRT && (
                    <ClienteSelector value={remitente} onChange={setRemitente} label="Remitente (quien envía la carga)" showDireccion={false} showEmail={false} />
                )}

                {/* Transportista (GRR público) */}
                {usaTransportista && (
                    <section className="space-y-3 rounded-2xl border border-border bg-card p-5">
                        <h2 className="text-sm font-semibold text-foreground">Transportista</h2>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label="RUC"><Input value={transportista.num_doc} onChange={(e) => setTransportista({ ...transportista, num_doc: e.target.value })} className="h-10 rounded-xl" /></Field>
                            <Field label="Razón social"><Input value={transportista.razon_social} onChange={(e) => setTransportista({ ...transportista, razon_social: e.target.value })} className="h-10 rounded-xl" /></Field>
                            <Field label="N° MTC (opcional)"><Input value={transMtc} onChange={(e) => setTransMtc(e.target.value)} className="h-10 rounded-xl" /></Field>
                        </div>
                    </section>
                )}

                {/* Vehículo + conductor */}
                {usaVehiculo && (
                    <section className="space-y-4 rounded-2xl border border-border bg-card p-5">
                        <h2 className="text-sm font-semibold text-foreground">Vehículo y conductor</h2>
                        <Field label="Placa del vehículo"><Input value={vehPlaca} onChange={(e) => setVehPlaca(e.target.value.toUpperCase())} placeholder="ABC123" className="h-10 max-w-xs rounded-xl" /></Field>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label="Tipo doc.">
                                <select value={cond.tipo_doc} onChange={(e) => setCond({ ...cond, tipo_doc: e.target.value })} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                                    <option value="1">DNI</option>
                                    <option value="6">RUC</option>
                                </select>
                            </Field>
                            <Field label="N° documento"><Input value={cond.num_doc} onChange={(e) => setCond({ ...cond, num_doc: e.target.value })} className="h-10 rounded-xl" /></Field>
                            <Field label="Licencia"><Input value={cond.licencia} onChange={(e) => setCond({ ...cond, licencia: e.target.value })} className="h-10 rounded-xl" /></Field>
                            <Field label="Nombres"><Input value={cond.nombres} onChange={(e) => setCond({ ...cond, nombres: e.target.value })} className="h-10 rounded-xl" /></Field>
                            <Field label="Apellidos"><Input value={cond.apellidos} onChange={(e) => setCond({ ...cond, apellidos: e.target.value })} className="h-10 rounded-xl" /></Field>
                        </div>
                    </section>
                )}

                {/* Doc relacionado (GRT) */}
                {esGRT && (
                    <section className="space-y-3 rounded-2xl border border-border bg-card p-5">
                        <h2 className="text-sm font-semibold text-foreground">Documento relacionado</h2>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label="Tipo">
                                <select value={docRel.tipo_codigo} onChange={(e) => setDocRel({ ...docRel, tipo_codigo: e.target.value })} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                                    {DOC_REL.map((d) => (<option key={d.code} value={d.code}>{d.label}</option>))}
                                </select>
                            </Field>
                            <Field label="Número (ej. F001-123)"><Input value={docRel.numero} onChange={(e) => setDocRel({ ...docRel, numero: e.target.value })} className="h-10 rounded-xl" /></Field>
                            <Field label="RUC emisor (si es guía)"><Input value={docRel.ruc_emisor} onChange={(e) => setDocRel({ ...docRel, ruc_emisor: e.target.value })} className="h-10 rounded-xl" /></Field>
                        </div>
                    </section>
                )}

                {/* Bienes a trasladar */}
                <section className="rounded-2xl border border-border bg-card shadow-soft">
                    <div className="flex items-center justify-between border-b border-border/60 px-5 py-3.5">
                        <span className="text-sm font-semibold text-foreground">Bienes a trasladar</span>
                        <button
                            type="button" onClick={addItem}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-border bg-secondary px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted"
                        >
                            <Plus className="size-3.5" /> Agregar ítem
                        </button>
                    </div>
                    <div className="flex flex-col gap-3 p-5">
                        {items.map((it, i) => (
                            <div key={i} className="grid gap-2 rounded-xl border border-border/60 bg-muted/20 p-3 sm:grid-cols-[1fr_110px_160px_40px]">
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs text-muted-foreground">Descripción</Label>
                                    <Input value={it.descripcion} onChange={(e) => updItem(i, 'descripcion', e.target.value)} placeholder="Ej: Caja de repuestos" className="h-9 rounded-lg text-sm" />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs text-muted-foreground">Cantidad</Label>
                                    <Input type="number" min={0} step="0.001" value={it.cantidad} onChange={(e) => updItem(i, 'cantidad', e.target.value)} className="h-9 rounded-lg text-right text-sm" />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs text-muted-foreground">Unidad</Label>
                                    <select
                                        value={it.unidad}
                                        onChange={(e) => updItem(i, 'unidad', e.target.value)}
                                        className="h-9 rounded-lg border border-input bg-card px-2 text-sm dark:border-border dark:bg-background"
                                    >
                                        {UNIDADES_GUIA.map((u) => (<option key={u.code} value={u.code}>{u.label}</option>))}
                                    </select>
                                </div>
                                <div className="flex items-end justify-center">
                                    <button
                                        type="button" onClick={() => removeItem(i)} disabled={items.length === 1} title="Quitar"
                                        className="flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:text-destructive disabled:opacity-30"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="flex flex-wrap items-center gap-3 pb-6">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-medium text-muted-foreground">Formato PDF</span>
                        <div className="flex rounded-xl border border-border p-0.5">
                            {PDF_FORMATOS.map(({ v, l }) => (
                                <button key={v} type="button" onClick={() => setPdfFormat(v)}
                                    className={`rounded-lg px-2.5 py-1 text-xs font-medium transition-colors ${pdfFormat === v ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-secondary'}`}>
                                    {l}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex-1" />

                    <Button type="button" onClick={() => submit(true)} disabled={submitting} className="gap-2 rounded-xl px-5">
                        {submitting && <Loader2 className="size-4 animate-spin" />}
                        Emitir y enviar a SUNAT
                    </Button>
                    <Button type="button" variant="outline" onClick={() => submit(false)} disabled={submitting} className="rounded-xl">
                        Guardar borrador
                    </Button>
                    <Button type="button" variant="ghost" onClick={() => router.visit('/sunat')} disabled={submitting} className="rounded-xl">Cancelar</Button>
                </div>
            </div>

            {/* Modal de PDF tras emitir */}
            {pdfModal && (
                <PdfPreviewModal
                    tipo={pdfModal.tipo}
                    id={pdfModal.id}
                    numero={pdfModal.numero}
                    initialFormat={pdfModal.formato ?? 'a4'}
                    onClose={() => setPdfModal(null)}
                />
            )}
        </SunatLayout>
    );
}
