import { Head, router } from '@inertiajs/react';
import { Loader2, Receipt } from 'lucide-react';
import { useState } from 'react';
import { ClienteSelector, type ClienteData } from '@/components/sunat/cliente-selector';
import { ItemsEditor, defaultItem, type ItemRow } from '@/components/sunat/items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

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
    const [moneda, setMoneda] = useState<'PEN' | 'USD'>('PEN');
    const [formaPago, setFormaPago] = useState('Contado');
    const [observacion, setObservacion] = useState('');

    const [cliente, setCliente] = useState<ClienteData | null>(null);

    const [items, setItems] = useState<ItemRow[]>([defaultItem()]);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const submit = () => {
        setError('');
        if (!cliente || !cliente.num_doc || !cliente.razon_social) return setError('Completa los datos del cliente.');
        if (items.some((it) => !it.descripcion || it.precio_unitario <= 0)) return setError('Completa los ítems (descripción y precio).');

        const payload = {
            fecha_emision: fecha,
            tipo_moneda: moneda,
            forma_pago: formaPago,
            observacion: observacion || undefined,
            cliente: {
                tipo_doc: cliente.tipo_doc,
                num_doc: cliente.num_doc,
                razon_social: cliente.razon_social,
                direccion: cliente.direccion || undefined,
            },
            items: items.map((it) => ({
                codigo: it.codigo || undefined,
                cod_producto_sunat: it.cod_producto_sunat || undefined,
                descripcion: it.descripcion,
                unidad: it.unidad,
                cantidad: it.cantidad,
                precio_unitario: it.precio_unitario,
                tip_afe_igv: it.tip_afe_igv,
                descuentos: it.descuento_pct > 0 ? [{
                    cod_tipo: '00',
                    factor: it.descuento_pct / 100,
                    monto: it.precio_unitario * it.cantidad * (it.descuento_pct / 100),
                    monto_base: it.precio_unitario * it.cantidad,
                }] : undefined,
            })),
        };

        setSubmitting(true);
        router.post('/sunat/nota-venta', payload, { onFinish: () => setSubmitting(false) });
    };

    return (
        <SunatLayout>
            <Head title="Nueva nota de venta" />

            <div className="mx-auto max-w-4xl space-y-5">
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
                        <select value={moneda} onChange={(e) => setMoneda(e.target.value as 'PEN' | 'USD')} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                            <option value="PEN">PEN - Soles</option>
                            <option value="USD">USD - Dólares</option>
                        </select>
                    </Field>
                    <Field label="Forma de pago">
                        <select value={formaPago} onChange={(e) => setFormaPago(e.target.value)} className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background">
                            <option value="Contado">Contado</option>
                            <option value="Credito">Crédito</option>
                        </select>
                    </Field>
                </section>

                <ClienteSelector value={cliente} onChange={setCliente} label="Cliente" />

                <ItemsEditor value={items} onChange={setItems} moneda={moneda} titulo="Ítems" />

                <section className="rounded-2xl border border-border bg-card p-5">
                    <Field label="Observación (opcional)">
                        <textarea
                            value={observacion}
                            onChange={(e) => setObservacion(e.target.value)}
                            rows={2}
                            placeholder="Nota o comentario interno"
                            className="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary/20 resize-none"
                        />
                    </Field>
                </section>

                <div className="flex items-center gap-3 pb-6">
                    <Button type="button" onClick={submit} disabled={submitting} className="gap-2 rounded-xl">
                        {submitting && <Loader2 className="size-4 animate-spin" />}
                        Crear nota de venta
                    </Button>
                    <Button type="button" variant="ghost" onClick={() => router.visit('/sunat')} disabled={submitting} className="rounded-xl">Cancelar</Button>
                </div>
            </div>
        </SunatLayout>
    );
}
