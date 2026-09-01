import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AlertCircle, Loader2 } from 'lucide-react';
import { ClienteSelector, type ClienteData } from '@/components/sunat/cliente-selector';
import { ItemsEditor, defaultItem, type ItemRow } from '@/components/sunat/items-editor';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import type { ClienteSunat, TenantSunat } from '@/types';

// ─── Catálogos ────────────────────────────────────────────────────────────────

type Props = {
    tenant: TenantSunat;
    clientes: ClienteSunat[];
};

// ─── Componente ──────────────────────────────────────────────────────────────

export default function NuevaCotizacion({ tenant, clientes }: Props) {

    const [fecha, setFecha]               = useState(new Date().toLocaleDateString('en-CA'));
    const [fechaVenc, setFechaVenc]        = useState('');
    const [moneda, setMoneda]              = useState<'PEN' | 'USD'>('PEN');
    const [observacion, setObservacion]    = useState('');

    const [cliente, setCliente] = useState<ClienteData | null>(null);

    const [items, setItems]   = useState<ItemRow[]>([defaultItem()]);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // ── Validación ──
    function validate() {
        const e: Record<string, string> = {};
        if (!cliente || !cliente.num_doc || !cliente.razon_social) e.cliente = 'Selecciona o ingresa el cliente.';
        if (items.some((it) => !it.descripcion)) e.items = 'Todos los ítems deben tener descripción.';
        setErrors(e);
        return Object.keys(e).length === 0;
    }

    // ── Envío ──
    function submit() {
        if (!validate()) return;
        setSubmitting(true);

        router.post('/sunat/cotizaciones', {
            fecha_emision:     fecha,
            fecha_vencimiento: fechaVenc || null,
            tipo_moneda:       moneda,
            cliente: {
                tipo_doc:     cliente?.tipo_doc ?? '6',
                num_doc:      cliente?.num_doc ?? '',
                razon_social: cliente?.razon_social ?? '',
                direccion:    cliente?.direccion || undefined,
                email:        cliente?.email     || undefined,
            },
            items: items.map((it) => ({
                descripcion:     it.descripcion,
                unidad:          it.unidad,
                cantidad:        it.cantidad,
                precio_unitario: it.precio_unitario,
                tip_afe_igv:     it.tip_afe_igv,
                descuentos: it.descuento_pct > 0 ? [{
                    cod_tipo:   '00',
                    factor:     it.descuento_pct / 100,
                    monto:      it.precio_unitario * it.cantidad * (it.descuento_pct / 100),
                    monto_base: it.precio_unitario * it.cantidad,
                }] : undefined,
            })),
            observacion: observacion || null,
        }, { onFinish: () => setSubmitting(false) });
    }

    return (
        <SunatLayout>
            <Head title="Nueva Cotización" />

            <div className="mx-auto max-w-[1400px]">
                <div className="mb-5 flex items-center gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">Nueva Cotización</h1>
                    {tenant.environment !== 'produccion' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800">
                            <AlertCircle className="size-3" /> Ambiente Beta
                        </span>
                    )}
                </div>

                <div className="mx-auto max-w-4xl">

                    {/* ══ FORMULARIO ══ */}
                    <div className="flex min-w-0 flex-col gap-5">

                        {/* ── 1. ENCABEZADO ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold">Datos de la cotización</span>
                            </div>
                            <div className="grid gap-4 p-5 sm:grid-cols-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Fecha de emisión</Label>
                                    <Input type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Válida hasta (opcional)</Label>
                                    <Input type="date" value={fechaVenc} min={fecha} onChange={(e) => setFechaVenc(e.target.value)} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Moneda</Label>
                                    <Combobox
                                        value={moneda}
                                        onChange={(v) => setMoneda(v as 'PEN' | 'USD')}
                                        options={[
                                            { value: 'PEN', label: 'PEN - Soles' },
                                            { value: 'USD', label: 'USD - Dólares' },
                                        ]}
                                        className="h-10 rounded-xl"
                                    />
                                </div>
                            </div>
                        </section>

                        {/* ── 2. CLIENTE ── */}
                        <ClienteSelector value={cliente} onChange={setCliente} label="Cliente" error={errors.cliente} />

                        {/* ── 3. ÍTEMS ── */}
                        <ItemsEditor value={items} onChange={setItems} moneda={moneda} error={errors.items} titulo="Servicios / Productos" />

                        {/* ── 4. OBSERVACIÓN ── */}
                        <section className="rounded-2xl border border-border bg-card shadow-soft">
                            <div className="border-b border-border/60 px-5 py-3.5">
                                <span className="text-sm font-semibold">Observaciones / Condiciones</span>
                            </div>
                            <div className="p-5">
                                <textarea
                                    value={observacion}
                                    onChange={(e) => setObservacion(e.target.value)}
                                    rows={3}
                                    placeholder="Condiciones de la propuesta, forma de pago, tiempo de entrega, notas adicionales..."
                                    className="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary/20 resize-none"
                                />
                            </div>
                        </section>

                        <div className="flex flex-wrap gap-3 pb-4">
                            <Button type="button" onClick={submit} disabled={submitting} className="gap-2 rounded-xl px-5">
                                {submitting && <Loader2 className="size-4 animate-spin" />}
                                Guardar Cotización
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => router.visit('/sunat/cotizaciones')} disabled={submitting} className="rounded-xl">
                                Cancelar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </SunatLayout>
    );
}
