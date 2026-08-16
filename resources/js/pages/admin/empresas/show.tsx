import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowLeft, Check, Copy, Download, Infinity as InfinityIcon, KeyRound, Pencil, Power, Store, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { ConfettiBurst } from '@/components/ui/confetti-burst';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';

type Tenant = {
    id: number;
    ruc: string;
    razon_social: string;
    nombre_comercial: string | null;
    direccion: string | null;
    ubigeo: string | null;
    departamento: string | null;
    provincia: string | null;
    distrito: string | null;
    telefonos: string[];
    emails: string[];
    environment: string;
    tax_regime: string | null;
    plan: string;
    emission_mode: string;
    max_documents_month: number;
    is_active: boolean;
    webhook_url: string | null;
    has_certificado: boolean;
    logo_url: string | null;
    sire_enabled: boolean;
    created_at: string | null;
    sucursales_count: number;
    series_count: number;
    user: { id: number; name: string; email: string } | null;
};

type Credenciales = {
    api_key: string;
    api_secret: string;
    ruc: string;
    razon_social: string;
};

type Props = {
    tenant: Tenant;
    credencialesNuevas: Credenciales | null;
};

const breadcrumbs = (razon: string): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: razon, href: '#' },
];

export default function EmpresasShow({ tenant, credencialesNuevas }: Props) {
    const [credOpen, setCredOpen] = useState<boolean>(Boolean(credencialesNuevas));
    const [copied, setCopied] = useState<'key' | 'secret' | null>(null);
    const confirm = useConfirm();

    // Confeti solo cuando la empresa se acaba de REGISTRAR (no al regenerar).
    const flash = usePage<{ flash?: { empresa_creada?: boolean } }>().props.flash;
    const [celebrar, setCelebrar] = useState(false);
    useEffect(() => {
        if (flash?.empresa_creada) setCelebrar(true);
    }, [flash?.empresa_creada]);

    // Abrir el modal cada vez que Inertia entrega credencialesNuevas
    // (POST /regenerar-credenciales redirige al mismo show sin remontar,
    // así useState no reactualiza — hay que forzarlo con useEffect).
    useEffect(() => {
        if (credencialesNuevas) {
            setCredOpen(true);
        }
    }, [credencialesNuevas]);

    const copy = (text: string, which: 'key' | 'secret') => {
        navigator.clipboard.writeText(text);
        setCopied(which);
        setTimeout(() => setCopied(null), 1500);
    };

    // Genera y descarga un .txt con las credenciales. Todo pasa en el
    // navegador (Blob) — no hay endpoint ni correo, así evitamos los
    // filtros anti-spam del SMTP que bloqueaban el envío.
    const descargarTxt = () => {
        if (!credencialesNuevas) return;
        const c = credencialesNuevas;
        const contenido = [
            'CREDENCIALES DE API - Jorge Chavez API SUNAT',
            '===========================================',
            '',
            `Empresa: ${c.razon_social}`,
            `RUC:     ${c.ruc}`,
            '',
            '-- Credenciales de acceso --',
            `X-Api-Key:    ${c.api_key}`,
            `X-Api-Secret: ${c.api_secret}`,
            '',
            'Envía ambos como headers HTTP en cada request a la API.',
            '',
            'IMPORTANTE: guarda este archivo en un lugar seguro.',
            'El api_secret no se puede volver a mostrar. Si lo pierdes,',
            'deberás regenerar las credenciales desde el panel.',
            '',
            `Generado: ${new Date().toLocaleString('es-PE')}`,
            '',
        ].join('\r\n');

        const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `credenciales-api-${c.ruc}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    const regenerar = async () => {
        if (
            await confirm({
                title: '¿Regenerar credenciales?',
                description: 'Las credenciales anteriores dejarán de funcionar inmediatamente.',
                variant: 'danger',
                confirmText: 'Regenerar',
            })
        ) {
            router.post(`/admin/empresas/${tenant.id}/regenerar-credenciales`);
        }
    };

    const toggle = () => {
        router.post(`/admin/empresas/${tenant.id}/toggle`, {}, { preserveScroll: true });
    };

    const eliminar = async () => {
        if (
            await confirm({
                title: `¿Eliminar "${tenant.razon_social}"?`,
                description:
                    'Se borrarán DEFINITIVAMENTE la empresa y TODOS sus datos: comprobantes, series, ' +
                    'clientes, sucursales y archivos (XML/CDR/PDF). Esta acción es IRREVERSIBLE.',
                variant: 'danger',
                confirmText: 'Eliminar todo',
            })
        ) {
            router.delete(`/admin/empresas/${tenant.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social)}>
            <Head title={tenant.razon_social} />
            <ConfettiBurst show={celebrar} />

            {/* Modal credenciales */}
            {credencialesNuevas && (
                <Dialog open={credOpen} onOpenChange={setCredOpen}>
                    <DialogContent className="rounded-2xl border-border shadow-none sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <KeyRound className="size-5 text-muted-foreground" />
                                Credenciales de la API
                            </DialogTitle>
                            <DialogDescription>
                                Cópialas ahora: son las únicas que debes darle al cliente. El{' '}
                                <span className="font-medium text-foreground">api_secret</span> no se volverá a mostrar.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-3">
                            {/* Empresa */}
                            <div className="rounded-xl border border-border bg-muted/30 px-3.5 py-2.5">
                                <div className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                    Empresa
                                </div>
                                <div className="mt-0.5 text-sm font-medium">
                                    {credencialesNuevas.ruc} — {credencialesNuevas.razon_social}
                                </div>
                            </div>

                            {/* Credenciales */}
                            {([
                                { label: 'X-Api-Key', value: credencialesNuevas.api_key, which: 'key' as const },
                                { label: 'X-Api-Secret', value: credencialesNuevas.api_secret, which: 'secret' as const },
                            ]).map((c) => (
                                <div key={c.which} className="rounded-xl bg-muted/40 px-3.5 py-2.5">
                                    <div className="mb-1.5 flex items-center justify-between">
                                        <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                            {c.label}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => copy(c.value, c.which)}
                                            className="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        >
                                            {copied === c.which ? (
                                                <>
                                                    <Check className="size-3.5" /> Copiado
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="size-3.5" /> Copiar
                                                </>
                                            )}
                                        </button>
                                    </div>
                                    <div className="break-all font-mono text-xs leading-relaxed">{c.value}</div>
                                </div>
                            ))}
                        </div>

                        <DialogFooter className="gap-2 sm:justify-between">
                            <Button type="button" variant="secondary" onClick={descargarTxt}>
                                <Download className="size-4" />
                                Descargar .txt
                            </Button>
                            <Button onClick={() => setCredOpen(false)}>Ya guardé las credenciales</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}

            <div className="flex flex-1 flex-col gap-4 p-4">
                {(!tenant.emails || tenant.emails.length === 0) && !tenant.user && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
                        <strong>Sin destinatario para correos:</strong> esta empresa no tiene emails registrados ni un usuario asignado.
                        Las credenciales no se enviarán automáticamente hasta que agregues al menos un email en{' '}
                        <Link href={`/admin/empresas/${tenant.id}/editar`} className="underline font-medium">
                            Editar empresa
                        </Link>
                        .
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        {/* Logo de la empresa */}
                        <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted/40">
                            {tenant.logo_url ? (
                                <img
                                    src={tenant.logo_url}
                                    alt={`Logo ${tenant.razon_social}`}
                                    className="size-full object-contain p-1"
                                    onError={(e) => (e.currentTarget.style.display = 'none')}
                                />
                            ) : (
                                <Store className="size-6 text-muted-foreground/40" />
                            )}
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">{tenant.razon_social}</h1>
                            <p className="text-sm text-muted-foreground font-mono">{tenant.ruc}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" asChild>
                            <Link href="/admin/empresas">
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                        <Button variant="secondary" asChild>
                            <Link href={`/admin/empresas/${tenant.id}/editar`}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                        <Button variant="secondary" onClick={regenerar}>
                            <KeyRound className="size-4" />
                            Regenerar credenciales
                        </Button>
                        <Button variant={tenant.is_active ? 'destructive' : 'default'} onClick={toggle}>
                            <Power className="size-4" />
                            {tenant.is_active ? 'Desactivar' : 'Activar'}
                        </Button>
                        <Button variant="destructive" onClick={eliminar}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="p-6">
                            <h3 className="mb-4 text-base font-semibold">Identidad</h3>
                            <dl className="grid gap-4 text-sm md:grid-cols-2">
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Nombre comercial</dt>
                                    <dd>{tenant.nombre_comercial ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Dirección</dt>
                                    <dd>{tenant.direccion ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Ubigeo</dt>
                                    <dd className="font-mono">{tenant.ubigeo ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Depto / Prov / Distrito</dt>
                                    <dd>
                                        {[tenant.departamento, tenant.provincia, tenant.distrito]
                                            .filter(Boolean)
                                            .join(' / ') || '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Teléfonos</dt>
                                    <dd>{tenant.telefonos.length ? tenant.telefonos.join(', ') : '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Emails</dt>
                                    <dd>{tenant.emails.length ? tenant.emails.join(', ') : '—'}</dd>
                                </div>
                            </dl>
                        </Card>

                        <Card className="p-6">
                            <h3 className="mb-4 text-base font-semibold">SUNAT & régimen</h3>
                            <dl className="grid gap-4 text-sm md:grid-cols-2">
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Entorno</dt>
                                    <dd>
                                        <Badge
                                            variant={tenant.environment === 'production' ? 'default' : 'secondary'}
                                            className="uppercase text-[10px]"
                                        >
                                            {tenant.environment}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Régimen tributario</dt>
                                    <dd>{tenant.tax_regime ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Certificado</dt>
                                    <dd>{tenant.has_certificado ? '✔ Cargado' : '— No cargado'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">SIRE</dt>
                                    <dd>{tenant.sire_enabled ? '✔ Activo' : 'Inactivo'}</dd>
                                </div>
                                <div className="md:col-span-2">
                                    <dt className="text-xs uppercase text-muted-foreground">Webhook</dt>
                                    <dd className="truncate">{tenant.webhook_url ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase text-muted-foreground">Plan / Límite</dt>
                                    {tenant.emission_mode === 'unlimited' ? (
                                        <dd className="flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400">
                                            <InfinityIcon className="size-4" />
                                            Ilimitada
                                            <span className="font-normal text-muted-foreground">· plan {tenant.plan}</span>
                                        </dd>
                                    ) : (
                                        <dd className="uppercase">
                                            {tenant.plan} — {tenant.max_documents_month} docs/mes
                                        </dd>
                                    )}
                                </div>
                            </dl>
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="p-5">
                            <div className="mb-1 text-xs uppercase text-muted-foreground">Estado</div>
                            {tenant.is_active ? (
                                <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300">
                                    Activa
                                </Badge>
                            ) : (
                                <Badge variant="secondary">Inactiva</Badge>
                            )}
                            <div className="mt-3 text-xs text-muted-foreground">
                                Creada {tenant.created_at ? new Date(tenant.created_at).toLocaleString('es-PE') : '—'}
                            </div>
                        </Card>

                        <Card className="p-5">
                            <div className="mb-2 flex items-center justify-between">
                                <span className="text-sm font-semibold">Sucursales</span>
                                <Link
                                    href={`/admin/empresas/${tenant.id}/sucursales`}
                                    className="text-xs text-primary hover:underline"
                                >
                                    Gestionar →
                                </Link>
                            </div>
                            <div className="text-2xl font-bold">{tenant.sucursales_count}</div>
                        </Card>

                        <Card className="p-5">
                            <div className="mb-2 flex items-center justify-between">
                                <span className="text-sm font-semibold">Series</span>
                                <Link
                                    href={`/admin/empresas/${tenant.id}/series`}
                                    className="text-xs text-primary hover:underline"
                                >
                                    Gestionar →
                                </Link>
                            </div>
                            <div className="text-2xl font-bold">{tenant.series_count}</div>
                        </Card>

                        <Card className="p-5">
                            <div className="mb-1 text-xs uppercase text-muted-foreground">Usuario asignado</div>
                            {tenant.user ? (
                                <>
                                    <div className="text-sm">{tenant.user.name}</div>
                                    <div className="text-xs text-muted-foreground">{tenant.user.email}</div>
                                </>
                            ) : (
                                <div className="text-sm text-muted-foreground">Sin asignar</div>
                            )}
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
