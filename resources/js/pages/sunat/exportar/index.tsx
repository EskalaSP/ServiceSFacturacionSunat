import { Head } from '@inertiajs/react';
import { Check, Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

const DOCS = [
    { key: 'facturas', label: 'Facturas' },
    { key: 'boletas', label: 'Boletas' },
    { key: 'notas-credito', label: 'Notas de crédito' },
    { key: 'notas-debito', label: 'Notas de débito' },
];

const hoy = () => new Date().toISOString().split('T')[0];

export default function Exportar() {
    const [desde, setDesde] = useState(hoy());
    const [hasta, setHasta] = useState(hoy());
    const [tipo, setTipo] = useState('xml');
    const [docs, setDocs] = useState<string[]>(DOCS.map((d) => d.key));

    const toggleDoc = (k: string) => setDocs((p) => (p.includes(k) ? p.filter((x) => x !== k) : [...p, k]));

    const descargar = () => {
        const params = new URLSearchParams({ fecha_desde: desde, fecha_hasta: hasta, tipo, documentos: docs.join(',') });
        window.location.href = `/sunat/exportar/descargar?${params.toString()}`;
    };

    return (
        <SunatLayout>
            <Head title="Exportar comprobantes" />
            <div className="mx-auto max-w-2xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary"><Download className="size-5" /></span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Exportar comprobantes</h1>
                        <p className="text-sm text-muted-foreground">Descarga un ZIP con los XML/PDF de un rango de fechas.</p>
                    </div>
                </header>

                <section className="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid gap-1.5"><Label>Desde</Label><Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} /></div>
                        <div className="grid gap-1.5"><Label>Hasta</Label><Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} /></div>
                        <div className="grid gap-1.5">
                            <Label>Formato</Label>
                            <select value={tipo} onChange={(e) => setTipo(e.target.value)} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                                <option value="xml">XML</option>
                                <option value="pdf">PDF</option>
                                <option value="ambos">Ambos</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <Label className="mb-2 block">Documentos</Label>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {DOCS.map((d) => (
                                <label key={d.key} className="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm">
                                    <span className="relative flex size-4 items-center justify-center">
                                        <input type="checkbox" checked={docs.includes(d.key)} onChange={() => toggleDoc(d.key)} className="peer size-4 shrink-0 cursor-pointer appearance-none rounded border-2 border-muted-foreground/40 bg-transparent transition-colors checked:border-primary checked:bg-primary" />
                                        <Check className="pointer-events-none absolute size-3 text-white opacity-0 peer-checked:opacity-100" strokeWidth={3} />
                                    </span>
                                    {d.label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <Button type="button" onClick={descargar} disabled={docs.length === 0}><Download className="size-4" /> Descargar ZIP</Button>
                </section>
            </div>
        </SunatLayout>
    );
}
