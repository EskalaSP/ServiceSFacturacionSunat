import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type RetPercConfig = {
    titulo: string;
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

    const [entTipoDoc, setEntTipoDoc] = useState('6');
    const [entNumDoc, setEntNumDoc] = useState('');
    const [entNombre, setEntNombre] = useState('');
    const [entDireccion, setEntDireccion] = useState('');

    const [regimen, setRegimen] = useState(config.regimenes[0]?.code ?? '01');
    const [tasa, setTasa] = useState(String(config.regimenes[0]?.tasa ?? 3));

    const [docs, setDocs] = useState<Doc[]>([{ tipo_doc: '01', num_doc: '', fecha_emision: hoy(), imp_total: '', moneda: 'PEN' }]);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const lookup = async () => {
        const n = entNumDoc.trim();
        if (n.length !== 11 && n.length !== 8) return;
        try {
            const res = await fetch(`/sunat/buscar-ruc?numero=${encodeURIComponent(n)}`, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                if (data.razon_social) setEntNombre(data.razon_social);
                if (data.direccion) setEntDireccion(data.direccion);
                setEntTipoDoc(n.length === 11 ? '6' : '1');
            }
        } catch {
            /* silencioso */
        }
    };

    const cambiarRegimen = (code: string) => {
        setRegimen(code);
        const r = config.regimenes.find((x) => x.code === code);
        if (r) setTasa(String(r.tasa));
    };

    const updDoc = (i: number, k: keyof Doc, v: string) => setDocs((p) => p.map((d, idx) => (idx === i ? { ...d, [k]: v } : d)));
    const addDoc = () => setDocs((p) => [...p, { tipo_doc: '01', num_doc: '', fecha_emision: hoy(), imp_total: '', moneda: 'PEN' }]);
    const removeDoc = (i: number) => setDocs((p) => p.filter((_, idx) => idx !== i));

    const submit = () => {
        setError('');
        if (!serie) return setError('Ingresa la serie.');
        if (!entNumDoc || !entNombre) return setError(`Completa los datos del ${config.entidadLabel.toLowerCase()}.`);
        if (docs.some((d) => !d.num_doc || !d.imp_total)) return setError('Completa todos los documentos (número e importe).');

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const payload: Record<string, any> = {
            serie,
            fecha_emision: fechaEmision,
            observacion: observacion || undefined,
            [config.entidadKey]: { tipo_doc: entTipoDoc, num_doc: entNumDoc, razon_social: entNombre, direccion: entDireccion || undefined },
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
        };

        setSubmitting(true);
        router.post(config.postUrl, payload, { onFinish: () => setSubmitting(false) });
    };

    return (
        <div className="mx-auto max-w-3xl space-y-5">
            {error && <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</div>}

            <section className="grid gap-3 rounded-2xl border border-border bg-card p-5 sm:grid-cols-3">
                <Field label="Serie"><Input value={serie} onChange={(e) => setSerie(e.target.value.toUpperCase())} placeholder={config.seriePlaceholder} className="h-10 rounded-xl" /></Field>
                <Field label="Fecha de emisión"><Input type="date" value={fechaEmision} onChange={(e) => setFechaEmision(e.target.value)} className="h-10 rounded-xl" /></Field>
                <Field label="Observación"><Input value={observacion} onChange={(e) => setObservacion(e.target.value)} className="h-10 rounded-xl" /></Field>
            </section>

            <section className="space-y-3 rounded-2xl border border-border bg-card p-5">
                <h2 className="text-sm font-semibold text-foreground">{config.entidadLabel}</h2>
                <div className="grid gap-3 sm:grid-cols-4">
                    <Field label="Tipo doc.">
                        <select value={entTipoDoc} onChange={(e) => setEntTipoDoc(e.target.value)} className="h-10 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="6">RUC</option>
                            <option value="1">DNI</option>
                        </select>
                    </Field>
                    <Field label="Número"><Input value={entNumDoc} onChange={(e) => setEntNumDoc(e.target.value)} onBlur={lookup} className="h-10 rounded-xl" /></Field>
                    <Field label="Razón social / Nombre"><Input value={entNombre} onChange={(e) => setEntNombre(e.target.value)} className="h-10 rounded-xl" /></Field>
                    <Field label="Dirección"><Input value={entDireccion} onChange={(e) => setEntDireccion(e.target.value)} className="h-10 rounded-xl" /></Field>
                </div>
            </section>

            <section className="grid gap-3 rounded-2xl border border-border bg-card p-5 sm:grid-cols-2">
                <Field label="Régimen">
                    <select value={regimen} onChange={(e) => cambiarRegimen(e.target.value)} className="h-10 rounded-xl border border-border bg-background px-3 text-sm">
                        {config.regimenes.map((r) => (<option key={r.code} value={r.code}>{r.code} — {r.label}</option>))}
                    </select>
                </Field>
                <Field label="Tasa (%)"><Input type="number" min={0} step="0.01" value={tasa} onChange={(e) => setTasa(e.target.value)} className="h-10 rounded-xl" /></Field>
            </section>

            <section className="rounded-2xl border border-border bg-card p-5">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-foreground">Documentos relacionados</h2>
                    <Button type="button" size="sm" variant="secondary" onClick={addDoc}><Plus className="size-4" /> Agregar</Button>
                </div>
                <div className="space-y-2">
                    {docs.map((d, i) => (
                        <div key={i} className="grid grid-cols-[90px_1fr_130px_110px_auto] gap-2">
                            <select value={d.tipo_doc} onChange={(e) => updDoc(i, 'tipo_doc', e.target.value)} className="h-9 rounded-lg border border-border bg-background px-2 text-sm">
                                <option value="01">Factura</option>
                                <option value="03">Boleta</option>
                                <option value="12">Ticket</option>
                            </select>
                            <Input value={d.num_doc} onChange={(e) => updDoc(i, 'num_doc', e.target.value)} placeholder="F001-123" className="h-9 rounded-lg text-sm" />
                            <Input type="date" value={d.fecha_emision} onChange={(e) => updDoc(i, 'fecha_emision', e.target.value)} className="h-9 rounded-lg text-sm" />
                            <Input type="number" min={0} step="0.01" value={d.imp_total} onChange={(e) => updDoc(i, 'imp_total', e.target.value)} placeholder="Importe" className="h-9 rounded-lg text-right text-sm" />
                            <button type="button" onClick={() => removeDoc(i)} disabled={docs.length === 1} className="px-2 text-muted-foreground hover:text-destructive disabled:opacity-30">
                                <Trash2 className="size-4" />
                            </button>
                        </div>
                    ))}
                </div>
            </section>

            <div className="flex items-center gap-3 pb-6">
                <Button type="button" onClick={submit} disabled={submitting}>{submitting ? 'Emitiendo…' : 'Emitir y enviar a SUNAT'}</Button>
                <Button type="button" variant="ghost" onClick={() => router.visit('/sunat')} disabled={submitting}>Cancelar</Button>
            </div>
        </div>
    );
}
