import { Head, router } from '@inertiajs/react';
import { Plus, Receipt, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import type { TenantSunat } from '@/types';

type Item = { descripcion: string; cantidad: string; precio_unitario: string; unidad: string };
type Props = { tenant: TenantSunat; clientes: unknown[] };

const hoy = () => new Date().toISOString().split('T')[0];

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
            {children}
        </div>
    );
}

export default function NuevaNotaVenta() {
    const [fecha, setFecha] = useState(hoy());
    const [moneda, setMoneda] = useState('PEN');
    const [formaPago, setFormaPago] = useState('Contado');
    const [observacion, setObservacion] = useState('');

    const [cliTipoDoc, setCliTipoDoc] = useState('6');
    const [cliNumDoc, setCliNumDoc] = useState('');
    const [cliNombre, setCliNombre] = useState('');
    const [cliDireccion, setCliDireccion] = useState('');

    const [items, setItems] = useState<Item[]>([{ descripcion: '', cantidad: '1', precio_unitario: '', unidad: 'NIU' }]);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const lookup = async () => {
        const n = cliNumDoc.trim();
        if (n.length !== 11 && n.length !== 8) return;
        try {
            const res = await fetch(`/sunat/buscar-ruc?numero=${encodeURIComponent(n)}`, { headers: { Accept: 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                if (data.razon_social) setCliNombre(data.razon_social);
                if (data.direccion) setCliDireccion(data.direccion);
                setCliTipoDoc(n.length === 11 ? '6' : '1');
            }
        } catch {
            /* silencioso */
        }
    };

    const updItem = (i: number, k: keyof Item, v: string) => setItems((p) => p.map((it, idx) => (idx === i ? { ...it, [k]: v } : it)));
    const addItem = () => setItems((p) => [...p, { descripcion: '', cantidad: '1', precio_unitario: '', unidad: 'NIU' }]);
    const removeItem = (i: number) => setItems((p) => p.filter((_, idx) => idx !== i));

    const total = items.reduce((s, it) => s + (Number(it.cantidad) || 0) * (Number(it.precio_unitario) || 0), 0);

    const submit = () => {
        setError('');
        if (!cliNumDoc || !cliNombre) return setError('Completa los datos del cliente.');
        if (items.some((it) => !it.descripcion || !it.precio_unitario)) return setError('Completa los ítems (descripción y precio).');

        const payload = {
            fecha_emision: fecha,
            tipo_moneda: moneda,
            forma_pago: formaPago,
            observacion: observacion || undefined,
            cliente: { tipo_doc: cliTipoDoc, num_doc: cliNumDoc, razon_social: cliNombre, direccion: cliDireccion || undefined },
            items: items.map((it) => ({
                descripcion: it.descripcion,
                cantidad: Number(it.cantidad),
                precio_unitario: Number(it.precio_unitario),
                unidad: it.unidad || 'NIU',
                tip_afe_igv: '10',
            })),
        };

        setSubmitting(true);
        router.post('/sunat/nota-venta', payload, { onFinish: () => setSubmitting(false) });
    };

    return (
        <SunatLayout>
            <Head title="Nueva nota de venta" />

            <div className="mx-auto max-w-3xl space-y-5">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <Receipt className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Nueva nota de venta</h1>
                        <p className="text-sm text-muted-foreground">Documento interno (no se envía a SUNAT).</p>
                    </div>
                </header>

                {error && <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{error}</div>}

                <section className="grid gap-3 rounded-2xl border border-border bg-card p-5 sm:grid-cols-3">
                    <Field label="Fecha"><Input type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} className="h-10 rounded-xl" /></Field>
                    <Field label="Moneda">
                        <select value={moneda} onChange={(e) => setMoneda(e.target.value)} className="h-10 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="PEN">PEN — Soles</option>
                            <option value="USD">USD — Dólares</option>
                        </select>
                    </Field>
                    <Field label="Forma de pago">
                        <select value={formaPago} onChange={(e) => setFormaPago(e.target.value)} className="h-10 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="Contado">Contado</option>
                            <option value="Credito">Crédito</option>
                        </select>
                    </Field>
                </section>

                <section className="space-y-3 rounded-2xl border border-border bg-card p-5">
                    <h2 className="text-sm font-semibold text-foreground">Cliente</h2>
                    <div className="grid gap-3 sm:grid-cols-4">
                        <Field label="Tipo doc.">
                            <select value={cliTipoDoc} onChange={(e) => setCliTipoDoc(e.target.value)} className="h-10 rounded-xl border border-border bg-background px-3 text-sm">
                                <option value="6">RUC</option>
                                <option value="1">DNI</option>
                                <option value="0">Otros</option>
                            </select>
                        </Field>
                        <Field label="Número"><Input value={cliNumDoc} onChange={(e) => setCliNumDoc(e.target.value)} onBlur={lookup} className="h-10 rounded-xl" /></Field>
                        <Field label="Razón social / Nombre"><Input value={cliNombre} onChange={(e) => setCliNombre(e.target.value)} className="h-10 rounded-xl" /></Field>
                        <Field label="Dirección"><Input value={cliDireccion} onChange={(e) => setCliDireccion(e.target.value)} className="h-10 rounded-xl" /></Field>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-foreground">Ítems</h2>
                        <Button type="button" size="sm" variant="secondary" onClick={addItem}><Plus className="size-4" /> Agregar</Button>
                    </div>
                    <div className="space-y-2">
                        {items.map((it, i) => (
                            <div key={i} className="grid grid-cols-[1fr_80px_80px_110px_auto] gap-2">
                                <Input value={it.descripcion} onChange={(e) => updItem(i, 'descripcion', e.target.value)} placeholder="Descripción" className="h-9 rounded-lg text-sm" />
                                <Input value={it.unidad} onChange={(e) => updItem(i, 'unidad', e.target.value)} placeholder="NIU" className="h-9 rounded-lg text-sm" />
                                <Input type="number" min={0} step="0.001" value={it.cantidad} onChange={(e) => updItem(i, 'cantidad', e.target.value)} className="h-9 rounded-lg text-right text-sm" />
                                <Input type="number" min={0} step="0.01" value={it.precio_unitario} onChange={(e) => updItem(i, 'precio_unitario', e.target.value)} placeholder="Precio" className="h-9 rounded-lg text-right text-sm" />
                                <button type="button" onClick={() => removeItem(i)} disabled={items.length === 1} className="px-2 text-muted-foreground hover:text-destructive disabled:opacity-30">
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 text-right text-sm">
                        <span className="text-muted-foreground">Total: </span>
                        <span className="font-semibold tabular-nums">{moneda === 'USD' ? '$' : 'S/'} {total.toFixed(2)}</span>
                    </div>
                </section>

                <div className="flex items-center gap-3 pb-6">
                    <Button type="button" onClick={submit} disabled={submitting}>{submitting ? 'Guardando…' : 'Crear nota de venta'}</Button>
                    <Button type="button" variant="ghost" onClick={() => router.visit('/sunat')} disabled={submitting}>Cancelar</Button>
                </div>
            </div>
        </SunatLayout>
    );
}
