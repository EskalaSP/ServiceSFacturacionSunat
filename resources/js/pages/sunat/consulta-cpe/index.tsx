import { Head } from '@inertiajs/react';
import { CheckCircle2, Search, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

type Prefill = {
    tipo_doc: string;
    serie: string;
    correlativo: string;
    fecha_emision: string | null;
    monto: string | null;
} | null;

type Props = { ruc_emisor: string; prefill?: Prefill };

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Resultado = { ok: boolean; data?: any; error?: string };

export default function ConsultaCpe({ ruc_emisor, prefill }: Props) {
    const [tipoDoc, setTipoDoc] = useState(prefill?.tipo_doc ?? '01');
    const [serie, setSerie] = useState(prefill?.serie ?? '');
    const [correlativo, setCorrelativo] = useState(prefill?.correlativo ?? '');
    const [fecha, setFecha] = useState(prefill?.fecha_emision ?? '');
    const [monto, setMonto] = useState(prefill?.monto ?? '');
    const [loading, setLoading] = useState(false);
    const [res, setRes] = useState<Resultado | null>(null);

    const consultar = async () => {
        setLoading(true);
        setRes(null);
        try {
            const params = new URLSearchParams({
                ruc_emisor,
                tipo_doc: tipoDoc,
                serie,
                correlativo: String(Number(correlativo)),
                fecha_emision: fecha,
                monto: String(Number(monto)),
            });
            const r = await fetch(`/sunat/consulta-cpe/buscar?${params.toString()}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            setRes(await r.json());
        } catch (e) {
            setRes({ ok: false, error: String(e) });
        } finally {
            setLoading(false);
        }
    };

    return (
        <SunatLayout>
            <Head title="Consultar comprobante" />
            <div className="mx-auto max-w-2xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary"><Search className="size-5" /></span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Consultar en SUNAT</h1>
                        <p className="text-sm text-muted-foreground">Estado de un comprobante (Consulta Integrada CPE).</p>
                    </div>
                </header>

                <section className="grid gap-3 rounded-xl border border-border bg-card p-5 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label>Tipo</Label>
                        <select value={tipoDoc} onChange={(e) => setTipoDoc(e.target.value)} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                            <option value="01">Factura</option>
                            <option value="03">Boleta</option>
                            <option value="07">Nota de crédito</option>
                            <option value="08">Nota de débito</option>
                        </select>
                    </div>
                    <div className="grid gap-1.5"><Label>Serie</Label><Input value={serie} onChange={(e) => setSerie(e.target.value.toUpperCase())} placeholder="F001" maxLength={4} /></div>
                    <div className="grid gap-1.5"><Label>Correlativo</Label><Input type="number" min={1} value={correlativo} onChange={(e) => setCorrelativo(e.target.value)} placeholder="123" /></div>
                    <div className="grid gap-1.5"><Label>Fecha (dd/mm/aaaa)</Label><Input value={fecha} onChange={(e) => setFecha(e.target.value)} placeholder="19/08/2026" /></div>
                    <div className="grid gap-1.5"><Label>Monto total</Label><Input type="number" min={0} step="0.01" value={monto} onChange={(e) => setMonto(e.target.value)} placeholder="472.00" /></div>
                    <div className="flex items-end">
                        <Button type="button" onClick={consultar} disabled={loading || !serie || !correlativo || !fecha || !monto}>{loading ? 'Consultando…' : 'Consultar'}</Button>
                    </div>
                </section>

                {res && (
                    <section className={`rounded-xl border p-5 ${res.ok ? 'border-success/30 bg-success/5' : 'border-destructive/30 bg-destructive/5'}`}>
                        <div className="mb-2 flex items-center gap-2 font-semibold">
                            {res.ok ? <CheckCircle2 className="size-5 text-success" /> : <XCircle className="size-5 text-destructive" />}
                            {res.ok ? 'Respuesta de SUNAT' : 'No se pudo consultar'}
                        </div>
                        {res.ok ? (
                            <pre className="overflow-x-auto rounded-lg bg-muted/40 p-3 text-xs">{JSON.stringify(res.data, null, 2)}</pre>
                        ) : (
                            <p className="text-sm text-destructive">{res.error}</p>
                        )}
                    </section>
                )}

                <p className="text-xs text-muted-foreground">
                    Requiere las credenciales de Consulta Integrada CPE (client_id/client_secret) configuradas en tu empresa.
                </p>
            </div>
        </SunatLayout>
    );
}
