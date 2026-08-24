import { Head, router } from '@inertiajs/react';
import { Ban, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

type Doc = {
    tipo_documento: string;
    tipo_label: string;
    serie: string;
    correlativo: string;
    numero: string;
    contraparte: string | null;
    fecha_emision: string;
};

type Props = { documentos: Doc[] };

export default function NuevaReversion({ documentos }: Props) {
    const [sel, setSel] = useState<string | null>(null);
    const [motivo, setMotivo] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const doc = documentos.find((d) => d.numero === sel) ?? null;

    const revertir = () => {
        if (!doc || !motivo.trim()) return;
        if (!confirm(`¿Revertir ${doc.tipo_label} ${doc.numero}? Envía una reversión a SUNAT y no se puede deshacer.`)) return;

        setSubmitting(true);
        router.post(
            '/sunat/reversiones',
            { tipo_documento: doc.tipo_documento, serie: doc.serie, correlativo: doc.correlativo, motivo, fecha_generacion: doc.fecha_emision },
            { onFinish: () => setSubmitting(false) },
        );
    };

    return (
        <SunatLayout>
            <Head title="Revertir retención / percepción" />

            <div className="mx-auto max-w-3xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
                        <Undo2 className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Reversión</h1>
                        <p className="text-sm text-muted-foreground">Da de baja una retención o percepción aceptada por SUNAT.</p>
                    </div>
                </header>

                {documentos.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        No hay retenciones ni percepciones aceptadas para revertir.
                    </p>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-xl border border-border">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2"></th>
                                        <th className="px-3 py-2 font-medium">Documento</th>
                                        <th className="px-3 py-2 font-medium">Contraparte</th>
                                        <th className="px-3 py-2 font-medium">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {documentos.map((d) => (
                                        <tr key={d.numero} className={`cursor-pointer border-b border-border/60 last:border-0 hover:bg-muted/20 ${sel === d.numero ? 'bg-accent/40' : ''}`} onClick={() => setSel(d.numero)}>
                                            <td className="px-3 py-2.5">
                                                <input type="radio" name="doc" checked={sel === d.numero} onChange={() => setSel(d.numero)} className="accent-primary" />
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <div className="font-medium text-foreground">{d.numero}</div>
                                                <div className="text-xs text-muted-foreground">{d.tipo_label}</div>
                                            </td>
                                            <td className="px-3 py-2.5 text-muted-foreground">{d.contraparte ?? '—'}</td>
                                            <td className="px-3 py-2.5 tabular-nums text-muted-foreground">{d.fecha_emision}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-5">
                            <div className="grid gap-2">
                                <Label htmlFor="motivo">Motivo de la reversión</Label>
                                <Input id="motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Ej: Documento emitido por error" maxLength={255} />
                            </div>
                            <div className="mt-4">
                                <Button type="button" variant="destructive" onClick={revertir} disabled={submitting || !doc || !motivo.trim()}>
                                    <Ban className="size-4" />
                                    {doc ? `Revertir ${doc.numero}` : 'Selecciona un documento'}
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </SunatLayout>
    );
}
