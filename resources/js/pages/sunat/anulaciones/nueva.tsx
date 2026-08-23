import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Ban } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

type DocAnulable = {
    tipo_documento: string;
    tipo_label: string;
    serie: string;
    correlativo: string;
    numero: string;
    cliente: string | null;
    total: number;
    moneda: string;
    fecha_emision: string;
};

type Props = {
    documentos: DocAnulable[];
};

function fmt(n: number) {
    return new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

export default function NuevaAnulacion({ documentos }: Props) {
    const [sel, setSel] = useState<string | null>(null);
    const [motivo, setMotivo] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const doc = documentos.find((d) => d.numero === sel) ?? null;

    const anular = () => {
        if (!doc || !motivo.trim()) return;
        if (!confirm(`¿Anular ${doc.tipo_label} ${doc.numero}? Esto envía una comunicación de baja a SUNAT y no se puede deshacer.`)) return;

        setSubmitting(true);
        router.post(
            '/sunat/anulaciones',
            {
                tipo_documento: doc.tipo_documento,
                serie: doc.serie,
                correlativo: doc.correlativo,
                motivo,
                fecha_generacion: doc.fecha_emision,
            },
            { onFinish: () => setSubmitting(false) },
        );
    };

    return (
        <SunatLayout>
            <Head title="Anular comprobante" />

            <div className="mx-auto max-w-3xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
                        <Ban className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Anular comprobante</h1>
                        <p className="text-sm text-muted-foreground">
                            Comunicación de baja a SUNAT para facturas y notas aceptadas (dentro de 7 días).
                        </p>
                    </div>
                </header>

                <div className="flex items-start gap-2 rounded-xl border border-warning/40 bg-warning/5 px-4 py-3 text-sm text-foreground">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
                    <p>
                        Las <strong>boletas</strong> no se anulan aquí — van por resumen diario. La anulación es
                        <strong> irreversible</strong> una vez aceptada por SUNAT.
                    </p>
                </div>

                {documentos.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        No hay comprobantes anulables (aceptados y dentro de los últimos 7 días).
                    </p>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-xl border border-border">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2"></th>
                                        <th className="px-3 py-2 font-medium">Documento</th>
                                        <th className="px-3 py-2 font-medium">Cliente</th>
                                        <th className="px-3 py-2 font-medium">Fecha</th>
                                        <th className="px-3 py-2 text-right font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {documentos.map((d) => (
                                        <tr
                                            key={d.numero}
                                            className={`cursor-pointer border-b border-border/60 last:border-0 hover:bg-muted/20 ${
                                                sel === d.numero ? 'bg-accent/40' : ''
                                            }`}
                                            onClick={() => setSel(d.numero)}
                                        >
                                            <td className="px-3 py-2.5">
                                                <input
                                                    type="radio"
                                                    name="doc"
                                                    checked={sel === d.numero}
                                                    onChange={() => setSel(d.numero)}
                                                    className="accent-primary"
                                                />
                                            </td>
                                            <td className="px-3 py-2.5">
                                                <div className="font-medium text-foreground">{d.numero}</div>
                                                <div className="text-xs text-muted-foreground">{d.tipo_label}</div>
                                            </td>
                                            <td className="px-3 py-2.5 text-muted-foreground">{d.cliente ?? '—'}</td>
                                            <td className="px-3 py-2.5 tabular-nums text-muted-foreground">{d.fecha_emision}</td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {d.moneda === 'USD' ? '$' : 'S/'} {fmt(d.total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-5">
                            <div className="grid gap-2">
                                <Label htmlFor="motivo">Motivo de la anulación</Label>
                                <Input
                                    id="motivo"
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    placeholder="Ej: Error en el monto / cliente equivocado"
                                    maxLength={255}
                                />
                            </div>
                            <div className="mt-4">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={anular}
                                    disabled={submitting || !doc || !motivo.trim()}
                                >
                                    <Ban className="size-4" />
                                    {doc ? `Anular ${doc.numero}` : 'Selecciona un comprobante'}
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </SunatLayout>
    );
}
