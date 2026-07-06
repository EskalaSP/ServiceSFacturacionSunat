import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowLeft, Download, KeyRound, Pencil, Power } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    max_documents_month: number;
    is_active: boolean;
    webhook_url: string | null;
    has_certificado: boolean;
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

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social)}>
            <Head title={tenant.razon_social} />

            {/* Modal credenciales */}
            {credencialesNuevas && (
                <Dialog open={credOpen} onOpenChange={setCredOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <KeyRound className="size-5" />
                                Credenciales de la API
                            </DialogTitle>
                            <DialogDescription>
                                Copia estas credenciales ahora. Son las únicas que debes darle al cliente.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
                            ⚠️ El <strong>api_secret</strong> no se volverá a mostrar. Descarga el archivo o cópialo antes de cerrar.
                        </div>

                        <div className="space-y-3">
                            <div>
                                <div className="mb-1 text-xs font-medium uppercase text-muted-foreground">Empresa</div>
                                <div className="text-sm">
                                    {credencialesNuevas.ruc} — {credencialesNuevas.razon_social}
                                </div>
                            </div>
                            <div>
                                <div className="mb-1 text-xs font-medium uppercase text-muted-foreground">X-Api-Key</div>
                                <div className="flex gap-2">
                                    <Input value={credencialesNuevas.api_key} readOnly className="font-mono text-xs" />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => copy(credencialesNuevas.api_key, 'key')}
                                    >
                                        {copied === 'key' ? 'Copiado ✓' : 'Copiar'}
                                    </Button>
                                </div>
                            </div>
                            <div>
                                <div className="mb-1 text-xs font-medium uppercase text-muted-foreground">X-Api-Secret</div>
                                <div className="flex gap-2">
                                    <Input value={credencialesNuevas.api_secret} readOnly className="font-mono text-xs" />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => copy(credencialesNuevas.api_secret, 'secret')}
                                    >
                                        {copied === 'secret' ? 'Copiado ✓' : 'Copiar'}
                                    </Button>
                                </div>
                            </div>
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
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">{tenant.razon_social}</h1>
                        <p className="text-sm text-muted-foreground font-mono">{tenant.ruc}</p>
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
                                    <dd className="uppercase">
                                        {tenant.plan} — {tenant.max_documents_month} docs/mes
                                    </dd>
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
