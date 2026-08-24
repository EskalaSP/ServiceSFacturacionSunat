import { router, usePage } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ClienteSelector, type ClienteData } from '@/components/sunat/cliente-selector';
import { PdfPreviewModal } from '@/components/sunat/pdf-preview-modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type RetPercConfig = {
    titulo: string;
    /** '20' (retención) | '40' (percepción) — tipo para el PDF */
    docTipo: string;
    /** 'proveedor' | 'cliente' */
    entidadKey: string;
    entidadLabel: string;
    /** 'pagos' | 'cobros' */
    pagosKey: string;
    /** 'fecha_retencion' | 'fecha_percepcion' */
    fechaKey: string;
    seriePlaceholder: string;
    postUrl: string;
    regimenes: { code: string; label: string; tasa: number }[];
};

type Emitido = { tipo: string; id: number; numero: string; formato?: string };

const PDF_FORMATOS = [{ v: 'a4', l: 'A4' }, { v: 'a5', l: 'A5' }, { v: 'ticket-80', l: '80mm' }, { v: 'ticket-58', l: '58mm' }];

type Doc = { tipo_doc: string; num_doc: string; fecha_emision: string; imp_total: string; moneda: string };

const hoy = () => new Date().toISOString().split('T')[0];

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
            {children}
        </div>
    );
}

export default function RetPercForm({ config }: { config: RetPercConfig }) {
    const [serie, setSerie] = useState('');
    const [fechaEmision, setFechaEmision] = useState(hoy());
    const [observacion, setObservacion] = useState('');

    const [entidad, setEntidad] = useState<ClienteData | null>(null);

    const [regimen, setRegimen] = useState(config.regimenes[0]?.code ?? '01');
    const [tasa, setTasa] = useState(String(config.regimenes[0]?.tasa ?? 3));

    const [docs, setDocs] = useState<Doc[]>([{ tipo_doc: '01', num_doc: '', fecha_emision: hoy(), imp_total: '', moneda: 'PEN' }]);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    // PDF: formato + modal tras emitir
    const { props: pageProps } = usePage();
    const [pdfFormat, setPdfFormat] = useState('a4');
    const [pdfModal, setPdfModal] = useState<Emitido | null>(null);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const emitidoFlash = (pageProps as any)?.flash?.emitido as Emitido | undefined;
    useEffect(() => {
        if (emitidoFlash?.id) setPdfModal(emitidoFlash);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [emitidoFlash?.id]);

    const cambiarRegimen = (code: string) => {
        setRegimen(code);
        const r = config.regimenes.find((x) => x.code === code);
        if (r) setTasa(String(r.tasa));
    };

    const updDoc = (i: number, k: keyof Doc, v: string) => setDocs((p) => p.map((d, idx) => (idx === i ? { ...d, [k]: v } : d)));
    const addDoc = () => setDocs((p) => [...p, { tipo_doc: '01', num_doc: '', fecha_emision: hoy(), imp_total: '', moneda: 'PEN' }]);
    const removeDoc = (i: number) => setDocs((p) => p.filter((_, idx) => idx !== i));

    const submit = (enviar: boolean) => {
        setError('');
        if (!serie) return setError('Ingresa la serie.');
        if (!entidad?.num_doc || !entidad?.razon_social) return setError(`Completa los datos del ${config.entidadLabel.toLowerCase()}.`);
        if (docs.some((d) => !d.num_doc || !d.imp_total)) return setError('Completa todos los documentos (número e importe).');

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const payload: Record<string, any> = {
            serie,
            fecha_emision: fechaEmision,
            observacion: observacion || undefined,
            [config.entidadKey]: {
                tipo_doc: entidad.tipo_doc,
                num_doc: entidad.num_doc,
                razon_social: entidad.razon_social,
                direccion: entidad.direccion || undefined,
            },
            regimen,
            tasa: Number(tasa),
            documentos: docs.map((d) => ({
                tipo_doc: d.tipo_doc,
                num_doc: d.num_doc,
                fecha_emision: d.fecha_emision,
                imp_total: Number(d.imp_total),
                moneda: d.moneda,
                [config.pagosKey]: [{ importe: Number(d.imp_total), fecha: hoy(), moneda: d.moneda }],
                [config.fechaKey]: hoy(),
            })),
            enviar_automatico: enviar,
            pdf_format: pdfFormat,
        };

        setSubmitting(true);
        router.post(config.postUrl, payload, { onFinish: () => setSubmitting(false) });
    };

    return (
        <div className="mx-auto max-w-4xl space-y-5">
            {error && <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</div>}

            {/* Datos del comprobante */}
            <section className="rounded-2xl border border-border bg-card shadow-soft">
                <div className="border-b border-border/60 px-5 py-3.5">
                    <span className="text-sm font-semibold text-foreground">Datos del comprobante</span>
                </div>
                <div className="grid gap-4 p-5 sm:grid-cols-3">
                    <Field label="Serie"><Input value={serie} onChange={(e) => setSerie(e.target.value.toUpperCase())} placeholder={config.seriePlaceholder} maxLength={4} className="h-10 rounded-xl" /></Field>
                    <Field label="Fecha de emisión"><Input type="date" value={fechaEmision} onChange={(e) => setFechaEmision(e.target.value)} className="h-10 rounded-xl" /></Field>
                    <Field label="Observación"><Input value={observacion} onChange={(e) => setObservacion(e.target.value)} placeholder="Opcional" className="h-10 rounded-xl" /></Field>
                </div>
            </section>

            {/* Entidad (proveedor / cliente) — búsqueda centralizada con token */}
            <ClienteSelector value={entidad} onChange={setEntidad} label={config.entidadLabel} showEmail={false} />

            {/* Régimen y tasa */}
            <section className="rounded-2xl border border-border bg-card shadow-soft">
                <div className="border-b border-border/60 px-5 py-3.5">
                    <span className="text-sm font-semibold text-foreground">Régimen y tasa</span>
                </div>
                <div className="grid gap-4 p-5 sm:grid-cols-2">
                    <Field label="Régimen">
                        <select value={regimen} onChange={(e) => cambiarRegimen(e.target.value)} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                            {config.regimenes.map((r) => (<option key={r.code} value={r.code}>{r.code} - {r.label}</option>))}
                        </select>
                    </Field>
                    <Field label="Tasa (%)"><Input type="number" min={0} step="0.01" value={tasa} onChange={(e) => setTasa(e.target.value)} className="h-10 rounded-xl" /></Field>
                </div>
            </section>

            {/* Documentos relacionados */}
            <section className="rounded-2xl border border-border bg-card shadow-soft">
                <div className="flex items-center justify-between border-b border-border/60 px-5 py-3.5">
                    <span className="text-sm font-semibold text-foreground">Documentos relacionados</span>
                    <button
                        type="button" onClick={addDoc}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-border bg-secondary px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted"
                    >
                        <Plus className="size-3.5" /> Agregar ítem
                    </button>
                </div>
                <div className="flex flex-col gap-3 p-5">
                    {docs.map((d, i) => (
                        <div key={i} className="grid gap-2 rounded-xl border border-border/60 bg-muted/20 p-3 sm:grid-cols-[130px_1fr_150px_130px_40px]">
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs text-muted-foreground">Tipo</Label>
                                <select value={d.tipo_doc} onChange={(e) => updDoc(i, 'tipo_doc', e.target.value)} className="h-9 rounded-lg border border-input bg-card px-2 text-sm dark:border-border dark:bg-background">
                                    <option value="01">Factura</option>
                                    <option value="03">Boleta</option>
                                    <option value="12">Ticket</option>
                                </select>
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs text-muted-foreground">Número</Label>
                                <Input value={d.num_doc} onChange={(e) => updDoc(i, 'num_doc', e.target.value)} placeholder="F001-123" className="h-9 rounded-lg text-sm" />
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs text-muted-foreground">Fecha</Label>
                                <Input type="date" value={d.fecha_emision} onChange={(e) => updDoc(i, 'fecha_emision', e.target.value)} className="h-9 rounded-lg text-sm" />
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs text-muted-foreground">Importe</Label>
                                <Input type="number" min={0} step="0.01" value={d.imp_total} onChange={(e) => updDoc(i, 'imp_total', e.target.value)} placeholder="0.00" className="h-9 rounded-lg text-right text-sm" />
                            </div>
                            <div className="flex items-end justify-center">
                                <button type="button" onClick={() => removeDoc(i)} disabled={docs.length === 1} title="Quitar" className="flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:text-destructive disabled:opacity-30">
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

            {/* Modal de PDF tras emitir */}
            {pdfModal && (
                <PdfPreviewModal
                    tipo={pdfModal.tipo || config.docTipo}
                    id={pdfModal.id}
                    numero={pdfModal.numero}
                    initialFormat={pdfModal.formato ?? 'a4'}
                    onClose={() => setPdfModal(null)}
                />
            )}
        </div>
    );
}
