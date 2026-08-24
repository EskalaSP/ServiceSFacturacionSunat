import { Head, router } from '@inertiajs/react';
import { Hash, Plus, Power, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

type Serie = { id: number; tipo_documento: string; tipo_nombre: string; serie: string; proximo: number; is_active: boolean };
type Props = { series: Serie[]; tipos: Record<string, string>; prefijos: Record<string, string[]> };

export default function SeriesIndex({ series, tipos, prefijos }: Props) {
    const tipoKeys = Object.keys(tipos);
    const [tipo, setTipo] = useState(tipoKeys[0] ?? '01');
    const [serie, setSerie] = useState('');
    const [proximo, setProximo] = useState('1');
    const [saving, setSaving] = useState(false);

    const hint = (prefijos[tipo] ?? []).join(' o ');

    const crear = () => {
        setSaving(true);
        router.post('/sunat/series', { tipo_documento: tipo, serie, correlativo: Number(proximo) }, {
            preserveScroll: true,
            onSuccess: () => { setSerie(''); setProximo('1'); },
            onFinish: () => setSaving(false),
        });
    };

    const toggle = (s: Serie) => router.post(`/sunat/series/${s.id}/toggle`, {}, { preserveScroll: true });
    const eliminar = (s: Serie) => {
        if (!confirm(`¿Eliminar la serie ${s.serie}?`)) return;
        router.delete(`/sunat/series/${s.id}`, { preserveScroll: true });
    };

    return (
        <SunatLayout>
            <Head title="Series" />
            <div className="mx-auto max-w-3xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary"><Hash className="size-5" /></span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Series y correlativos</h1>
                        <p className="text-sm text-muted-foreground">Define las series de tus comprobantes.</p>
                    </div>
                </header>

                <section className="rounded-xl border border-border bg-card p-5">
                    <h2 className="mb-4 flex items-center gap-2 font-semibold text-foreground"><Plus className="size-4" /> Nueva serie</h2>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label>Tipo de comprobante</Label>
                            <select value={tipo} onChange={(e) => setTipo(e.target.value)} className="h-10 rounded-xl border border-border bg-background px-3 text-sm">
                                {tipoKeys.map((k) => (<option key={k} value={k}>{k} — {tipos[k]}</option>))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Serie {hint && <span className="text-xs text-muted-foreground">(empieza con {hint})</span>}</Label>
                            <Input value={serie} onChange={(e) => setSerie(e.target.value.toUpperCase())} placeholder="F001" maxLength={4} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Próximo número</Label>
                            <Input type="number" min={1} value={proximo} onChange={(e) => setProximo(e.target.value)} />
                        </div>
                    </div>
                    <div className="mt-4">
                        <Button type="button" onClick={crear} disabled={saving || serie.length !== 4}><Plus className="size-4" /> Crear serie</Button>
                    </div>
                </section>

                <section className="overflow-x-auto rounded-xl border border-border">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Tipo</th>
                                <th className="px-3 py-2 font-medium">Serie</th>
                                <th className="px-3 py-2 text-right font-medium">Próximo</th>
                                <th className="px-3 py-2 font-medium">Estado</th>
                                <th className="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {series.length === 0 && (
                                <tr><td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">Sin series. Crea una arriba.</td></tr>
                            )}
                            {series.map((s) => (
                                <tr key={s.id} className="border-b border-border/60 last:border-0">
                                    <td className="px-3 py-2.5">{s.tipo_documento} — {s.tipo_nombre}</td>
                                    <td className="px-3 py-2.5 font-medium text-foreground">{s.serie}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums">{s.proximo}</td>
                                    <td className="px-3 py-2.5">{s.is_active ? <Badge variant="secondary">Activa</Badge> : <Badge variant="outline">Inactiva</Badge>}</td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex justify-end gap-1">
                                            <Button type="button" size="sm" variant="ghost" onClick={() => toggle(s)} title={s.is_active ? 'Desactivar' : 'Activar'}><Power className="size-4" /></Button>
                                            <Button type="button" size="sm" variant="ghost" onClick={() => eliminar(s)}><Trash2 className="size-4 text-destructive" /></Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </SunatLayout>
    );
}
