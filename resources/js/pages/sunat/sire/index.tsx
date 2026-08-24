import { Head, router } from '@inertiajs/react';
import { Database, Download, Power, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

type Ticket = {
    num_ticket: string;
    per_tributario: string;
    des_proceso: string | null;
    estado: string | null;
    estado_descripcion: string | null;
    finalizado: boolean;
    exitoso: boolean;
    archivo_disponible: boolean;
    created_at: string | null;
};

type Props = { sire_enabled: boolean; tickets: Ticket[] };

function csrf() {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

async function postJson(url: string, body: Record<string, unknown>) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    return r.json();
}

export default function SireIndex({ sire_enabled, tickets }: Props) {
    const [periodo, setPeriodo] = useState('');
    const [busy, setBusy] = useState<string | null>(null);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const [msg, setMsg] = useState<{ ok: boolean; text: string; data?: any } | null>(null);

    const accion = async (endpoint: string, label: string) => {
        if (!/^\d{6}$/.test(periodo)) {
            setMsg({ ok: false, text: 'Ingresa un periodo válido (AAAAMM, ej. 202608).' });
            return;
        }
        setBusy(label);
        setMsg(null);
        try {
            const res = await postJson(`/sunat/sire/${endpoint}`, { periodo });
            setMsg({ ok: res.ok, text: res.ok ? (res.mensaje ?? 'Listo.') : (res.error ?? 'Error'), data: res.data });
            if (res.ok) router.reload({ only: ['tickets'] });
        } finally {
            setBusy(null);
        }
    };

    const refrescar = async (num: string) => {
        setBusy(`t-${num}`);
        try {
            await postJson(`/sunat/sire/tickets/${num}/refrescar`, {});
            router.reload({ only: ['tickets'] });
        } finally {
            setBusy(null);
        }
    };

    return (
        <SunatLayout>
            <Head title="SIRE (RCE)" />
            <div className="mx-auto max-w-4xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary"><Database className="size-5" /></span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">SIRE — Registro de Compras (RCE)</h1>
                        <p className="text-sm text-muted-foreground">Propuesta, aceptación, preliminar y reconciliación con SUNAT.</p>
                    </div>
                </header>

                {/* Estado / activación */}
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-5">
                    <div className="flex items-center gap-3">
                        <span className="text-sm font-medium text-foreground">Estado SIRE:</span>
                        {sire_enabled ? <Badge variant="secondary">Activado</Badge> : <Badge variant="outline">Desactivado</Badge>}
                    </div>
                    {sire_enabled ? (
                        <Button type="button" variant="ghost" onClick={() => router.post('/sunat/sire/desactivar', {}, { preserveScroll: true })}>
                            <Power className="size-4" /> Desactivar
                        </Button>
                    ) : (
                        <Button type="button" onClick={() => router.post('/sunat/sire/activar', {}, { preserveScroll: true })}>
                            <Power className="size-4" /> Activar SIRE
                        </Button>
                    )}
                </section>

                {sire_enabled && (
                    <>
                        {/* Periodo + acciones */}
                        <section className="space-y-4 rounded-xl border border-border bg-card p-5">
                            <div className="grid gap-1.5 sm:max-w-xs">
                                <Label>Periodo tributario (AAAAMM)</Label>
                                <Input value={periodo} onChange={(e) => setPeriodo(e.target.value.replace(/\D/g, '').slice(0, 6))} placeholder="202608" />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" variant="secondary" disabled={busy !== null} onClick={() => accion('propuesta', 'propuesta')}>
                                    {busy === 'propuesta' ? 'Solicitando…' : 'Solicitar propuesta'}
                                </Button>
                                <Button type="button" variant="secondary" disabled={busy !== null} onClick={() => accion('aceptar', 'aceptar')}>
                                    {busy === 'aceptar' ? 'Aceptando…' : 'Aceptar propuesta'}
                                </Button>
                                <Button type="button" variant="secondary" disabled={busy !== null} onClick={() => accion('preliminar', 'preliminar')}>
                                    {busy === 'preliminar' ? 'Registrando…' : 'Registrar preliminar'}
                                </Button>
                                <Button type="button" variant="secondary" disabled={busy !== null} onClick={() => accion('reconciliar', 'reconciliar')}>
                                    {busy === 'reconciliar' ? 'Reconciliando…' : 'Reconciliar'}
                                </Button>
                            </div>

                            {msg && (
                                <div className={`rounded-lg border p-3 text-sm ${msg.ok ? 'border-success/30 bg-success/5 text-foreground' : 'border-destructive/30 bg-destructive/5 text-destructive'}`}>
                                    <p>{msg.text}</p>
                                    {msg.data && <pre className="mt-2 overflow-x-auto rounded bg-muted/40 p-2 text-xs">{JSON.stringify(msg.data, null, 2)}</pre>}
                                </div>
                            )}
                        </section>

                        {/* Tickets */}
                        <section className="space-y-3">
                            <h2 className="font-semibold text-foreground">Tickets recientes</h2>
                            {tickets.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">Sin tickets todavía.</p>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border border-border">
                                    <table className="w-full text-sm">
                                        <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
                                            <tr>
                                                <th className="px-3 py-2 font-medium">Ticket</th>
                                                <th className="px-3 py-2 font-medium">Periodo</th>
                                                <th className="px-3 py-2 font-medium">Proceso</th>
                                                <th className="px-3 py-2 font-medium">Estado</th>
                                                <th className="px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tickets.map((t) => (
                                                <tr key={t.num_ticket} className="border-b border-border/60 last:border-0">
                                                    <td className="px-3 py-2.5 font-mono text-xs">{t.num_ticket}</td>
                                                    <td className="px-3 py-2.5">{t.per_tributario}</td>
                                                    <td className="px-3 py-2.5 text-muted-foreground">{t.des_proceso ?? '—'}</td>
                                                    <td className="px-3 py-2.5">
                                                        {t.finalizado ? (
                                                            <Badge variant={t.exitoso ? 'secondary' : 'outline'}>{t.estado_descripcion ?? (t.exitoso ? 'OK' : 'Fin')}</Badge>
                                                        ) : (
                                                            <Badge variant="outline">{t.estado_descripcion ?? 'En proceso'}</Badge>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <div className="flex justify-end gap-1">
                                                            {!t.finalizado && (
                                                                <Button type="button" size="sm" variant="ghost" disabled={busy !== null} onClick={() => refrescar(t.num_ticket)} title="Refrescar estado">
                                                                    <RefreshCw className={`size-4 ${busy === `t-${t.num_ticket}` ? 'animate-spin' : ''}`} />
                                                                </Button>
                                                            )}
                                                            {t.archivo_disponible && (
                                                                <a href={`/sunat/sire/tickets/${t.num_ticket}/archivo`} className="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:text-foreground" title="Descargar archivo">
                                                                    <Download className="size-4" />
                                                                </a>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </>
                )}
            </div>
        </SunatLayout>
    );
}
