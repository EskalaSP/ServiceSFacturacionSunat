import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Calculator,
    CreditCard,
    Database,
    IdCard,
    Infinity as InfinityIcon,
    MapPin,
    Plus,
    ShieldCheck,
    Store,
    UserCircle,
    X,
    type LucideIcon,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PasswordInput } from '@/components/ui/password-input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import type { BreadcrumbItem } from '@/types';

type Tenant = {
    id: number | null;
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
    sol_user: string;
    environment: string;
    sire_enabled: boolean;
    sire_client_id: string | null;
    tax_regime: string;
    igv_rate_override: number | string | null;
    nrus_categoria: number | string | null;
    emission_mode: string;
    plan: string;
    max_documents_month: number;
    webhook_url: string | null;
    mensaje_agradecimiento: string | null;
    mensaje_promocional: string | null;
    cuentas_bancarias: Array<{ banco: string; tipo?: string; moneda?: string; numero: string; cci?: string }>;
    billeteras_digitales: Array<{ tipo: string; numero: string }>;
    user_id: number | null;
    is_active: boolean;
    has_certificado?: boolean;
    has_logo?: boolean;
};

type Option = { slug: string; name: string };
type UserOption = { id: number; name: string; email: string };

type Props = {
    tenant: Tenant;
    planes: Option[];
    usuarios: UserOption[];
    modo: 'crear' | 'editar';
    emisionGlobalIlimitada?: boolean;
};

const breadcrumbs = (modo: 'crear' | 'editar', razon?: string): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: modo === 'crear' ? 'Nueva empresa' : `Editar: ${razon ?? ''}`, href: '#' },
];

const Section = ({
    icon: Icon,
    title,
    subtitle,
    children,
}: {
    icon: LucideIcon;
    title: string;
    subtitle?: string;
    children: React.ReactNode;
}) => (
    <Card className="p-6">
        <div className="mb-5 flex items-start gap-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="size-5" />
            </div>
            <div className="flex-1">
                <h3 className="text-base font-semibold leading-tight">{title}</h3>
                {subtitle && <p className="mt-0.5 text-sm text-muted-foreground">{subtitle}</p>}
            </div>
        </div>
        {children}
    </Card>
);

export default function EmpresasForm({ tenant, planes, usuarios, modo, emisionGlobalIlimitada }: Props) {
    const editando = modo === 'editar';
    const { data, setData, post, put, processing, errors, progress } = useForm({
        ruc: tenant.ruc,
        razon_social: tenant.razon_social,
        nombre_comercial: tenant.nombre_comercial ?? '',
        direccion: tenant.direccion ?? '',
        ubigeo: tenant.ubigeo ?? '',
        departamento: tenant.departamento ?? '',
        provincia: tenant.provincia ?? '',
        distrito: tenant.distrito ?? '',
        // Si vienen vacíos en edición, muestro 1 input listo para escribir
        // en vez de solo el botón "Añadir" — evita el paso extra de clic.
        telefonos: (tenant.telefonos && tenant.telefonos.length > 0) ? tenant.telefonos : [''],
        emails: (tenant.emails && tenant.emails.length > 0) ? tenant.emails : [''],
        sol_user: tenant.sol_user ?? 'MODDATOS',
        sol_pass: '',
        environment: tenant.environment || 'beta',
        certificado: null as File | null,
        contrasena_certificado: '',
        sire_enabled: tenant.sire_enabled ?? false,
        sire_client_id: tenant.sire_client_id ?? '',
        sire_client_secret: '',
        tax_regime: tenant.tax_regime || 'general',
        igv_rate_override: tenant.igv_rate_override ?? '',
        nrus_categoria: tenant.nrus_categoria ?? '',
        emission_mode: tenant.emission_mode || 'plan',
        plan: tenant.plan || 'free',
        max_documents_month: Number(tenant.max_documents_month) || 20,
        webhook_url: tenant.webhook_url ?? '',
        logo: null as File | null,
        mensaje_agradecimiento: tenant.mensaje_agradecimiento ?? '',
        mensaje_promocional: tenant.mensaje_promocional ?? '',
        cuentas_bancarias: tenant.cuentas_bancarias ?? [],
        billeteras_digitales: tenant.billeteras_digitales ?? [],
        user_id: tenant.user_id ?? '',
        is_active: tenant.is_active ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // Solo forzamos multipart cuando realmente hay un archivo — con
        // JSON el flujo es más simple y evita el PUT+multipart que en
        // algunos setups deja el body vacío antes de llegar a Laravel.
        const hayArchivos = data.certificado instanceof File || data.logo instanceof File;
        const options = hayArchivos ? { forceFormData: true } : {};
        if (editando && tenant.id) {
            put(`/admin/empresas/${tenant.id}`, options);
        } else {
            post('/admin/empresas', options);
        }
    };

    const addRepeat = <K extends 'telefonos' | 'emails' | 'cuentas_bancarias' | 'billeteras_digitales'>(
        key: K,
        emptyItem: unknown,
    ) => {
        setData(key, [...(data[key] as unknown[]), emptyItem] as never);
    };

    const rmRepeat = <K extends 'telefonos' | 'emails' | 'cuentas_bancarias' | 'billeteras_digitales'>(
        key: K,
        i: number,
    ) => {
        const arr = [...(data[key] as unknown[])];
        arr.splice(i, 1);
        setData(key, arr as never);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(modo, tenant.razon_social)}>
            <Head title={editando ? 'Editar empresa' : 'Nueva empresa'} />

            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {editando ? `Editar: ${tenant.razon_social}` : 'Nueva empresa'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Configura todos los datos SUNAT, comerciales y de plan.
                        </p>
                    </div>
                    <Button variant="ghost" asChild>
                        <Link href="/admin/empresas">
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                {editando && (
                    <div className="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/20 dark:text-sky-300">
                        <strong>Editando datos existentes.</strong>{' '}
                        Los campos que ves con texto en gris <em>(Ej: ...)</em> están vacíos en la base de datos —
                        es solo texto de ejemplo para orientarte. Completa los que quieras y guarda; los campos vacíos quedan vacíos.
                    </div>
                )}

                {/* 1. Identidad */}
                <Section icon={IdCard} title="1. Identidad" subtitle="RUC y datos legales de la empresa">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="ruc">RUC *</Label>
                            <Input
                                id="ruc"
                                value={data.ruc}
                                onChange={(e) => setData('ruc', e.target.value)}
                                maxLength={11}
                                pattern="\d{11}"
                                required
                                readOnly={editando}
                                className={editando ? 'bg-muted' : ''}
                                placeholder="20512345678"
                            />
                            {errors.ruc && <p className="mt-1 text-xs text-red-600">{errors.ruc}</p>}
                            {!editando && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    11 dígitos, empieza con 10/15/17/20
                                </p>
                            )}
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="razon_social">Razón social *</Label>
                            <Input
                                id="razon_social"
                                value={data.razon_social}
                                onChange={(e) => setData('razon_social', e.target.value)}
                                required
                                maxLength={255}
                                placeholder="EMPRESA EJEMPLO S.A.C."
                            />
                            {errors.razon_social && <p className="mt-1 text-xs text-red-600">{errors.razon_social}</p>}
                        </div>
                        <div className="md:col-span-3">
                            <Label htmlFor="nombre_comercial">Nombre comercial</Label>
                            <Input
                                id="nombre_comercial"
                                value={data.nombre_comercial}
                                onChange={(e) => setData('nombre_comercial', e.target.value)}
                                maxLength={255}
                                placeholder="Ej: TIENDA MODAS AMÉRICA (opcional)"
                            />
                        </div>
                    </div>
                </Section>

                {/* 2. Ubicación */}
                <Section icon={MapPin} title="2. Ubicación" subtitle="Dirección fiscal y contacto">
                    <div className="grid gap-4 md:grid-cols-6">
                        <div className="md:col-span-6">
                            <Label htmlFor="direccion">Dirección fiscal</Label>
                            <Input
                                id="direccion"
                                value={data.direccion}
                                onChange={(e) => setData('direccion', e.target.value)}
                                maxLength={500}
                                placeholder="Av. Arequipa 1234, San Isidro"
                            />
                        </div>
                        <div>
                            <Label htmlFor="ubigeo">Ubigeo</Label>
                            <Input
                                id="ubigeo"
                                value={data.ubigeo}
                                onChange={(e) => setData('ubigeo', e.target.value)}
                                maxLength={6}
                                pattern="\d{6}"
                                placeholder="150101"
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="departamento">Departamento</Label>
                            <Input
                                id="departamento"
                                value={data.departamento}
                                onChange={(e) => setData('departamento', e.target.value)}
                                placeholder="LIMA"
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="provincia">Provincia</Label>
                            <Input
                                id="provincia"
                                value={data.provincia}
                                onChange={(e) => setData('provincia', e.target.value)}
                                placeholder="LIMA"
                            />
                        </div>
                        <div>
                            <Label htmlFor="distrito">Distrito</Label>
                            <Input
                                id="distrito"
                                value={data.distrito}
                                onChange={(e) => setData('distrito', e.target.value)}
                                placeholder="SAN ISIDRO"
                            />
                        </div>

                        <div className="md:col-span-3">
                            <Label>Teléfonos</Label>
                            {data.telefonos.map((tel, i) => (
                                <div key={i} className="mt-2 flex gap-2">
                                    <Input
                                        value={tel}
                                        onChange={(e) => {
                                            const arr = [...data.telefonos];
                                            arr[i] = e.target.value;
                                            setData('telefonos', arr);
                                        }}
                                        maxLength={20}
                                        placeholder="Ej: +51 999 999 999"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => rmRepeat('telefonos', i)}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            {data.telefonos.length < 5 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="mt-2"
                                    onClick={() => addRepeat('telefonos', '')}
                                >
                                    <Plus className="size-3" /> Añadir teléfono
                                </Button>
                            )}
                        </div>

                        <div className="md:col-span-3">
                            <Label>Emails</Label>
                            {data.emails.map((em, i) => (
                                <div key={i} className="mt-2 flex gap-2">
                                    <Input
                                        type="email"
                                        value={em}
                                        onChange={(e) => {
                                            const arr = [...data.emails];
                                            arr[i] = e.target.value;
                                            setData('emails', arr);
                                        }}
                                        maxLength={100}
                                        placeholder="Ej: ventas@empresa.com"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => rmRepeat('emails', i)}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            {data.emails.length < 5 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="mt-2"
                                    onClick={() => addRepeat('emails', '')}
                                >
                                    <Plus className="size-3" /> Añadir email
                                </Button>
                            )}
                        </div>
                    </div>
                </Section>

                {/* 3. Credenciales SUNAT */}
                <Section icon={ShieldCheck} title="3. Credenciales SUNAT" subtitle="Usuario SOL, certificado y entorno">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="sol_user">Usuario SOL *</Label>
                            <Input
                                id="sol_user"
                                value={data.sol_user}
                                onChange={(e) => setData('sol_user', e.target.value)}
                                required
                                maxLength={50}
                                placeholder="MODDATOS (para beta)"
                            />
                        </div>
                        <div>
                            <Label htmlFor="sol_pass">Clave SOL {editando ? '' : '*'}</Label>
                            <PasswordInput
                                id="sol_pass"
                                value={data.sol_pass}
                                onChange={(e) => setData('sol_pass', e.target.value)}
                                required={!editando}
                                minLength={4}
                                placeholder={editando ? '••••• (dejar vacío = sin cambios)' : 'MODDATOS (para beta)'}
                            />
                        </div>
                        <div>
                            <Label htmlFor="environment">Entorno *</Label>
                            <select
                                id="environment"
                                value={data.environment}
                                onChange={(e) => setData('environment', e.target.value)}
                                className="form-select"
                                required
                            >
                                <option value="beta">Beta (pruebas)</option>
                                <option value="production">Producción</option>
                            </select>
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="certificado">Certificado digital (.pfx / .p12 / .pem)</Label>
                            <Input
                                id="certificado"
                                type="file"
                                accept=".pfx,.p12,.pem"
                                onChange={(e) => setData('certificado', e.target.files?.[0] ?? null)}
                            />
                            {tenant.has_certificado && (
                                <p className="mt-1 text-xs text-emerald-600">✔ Certificado actual cargado</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="contrasena_certificado">Contraseña del certificado</Label>
                            <PasswordInput
                                id="contrasena_certificado"
                                value={data.contrasena_certificado}
                                onChange={(e) => setData('contrasena_certificado', e.target.value)}
                                placeholder="Contraseña del archivo .pfx"
                            />
                        </div>
                    </div>
                </Section>

                {/* 4. SIRE */}
                <Section icon={Database} title="4. SIRE (opcional)" subtitle="Registro de Compras Electrónico">
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="sire_enabled"
                                checked={data.sire_enabled}
                                onCheckedChange={(v) => setData('sire_enabled', v === true)}
                            />
                            <Label htmlFor="sire_enabled" className="font-normal">
                                Activar SIRE para esta empresa
                            </Label>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <Label htmlFor="sire_client_id">Client ID SIRE</Label>
                                <Input
                                    id="sire_client_id"
                                    value={data.sire_client_id}
                                    onChange={(e) => setData('sire_client_id', e.target.value)}
                                    maxLength={100}
                                    placeholder="Ej: 00abc123-4567-... (Client ID SIRE)"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="sire_client_secret">Client Secret SIRE</Label>
                                <PasswordInput
                                    id="sire_client_secret"
                                    value={data.sire_client_secret}
                                    onChange={(e) => setData('sire_client_secret', e.target.value)}
                                    placeholder={editando ? '••••• (dejar vacío = sin cambios)' : 'Client Secret otorgado por SUNAT SIRE'}
                                    maxLength={200}
                                />
                            </div>
                        </div>
                    </div>
                </Section>

                {/* 5. Régimen tributario */}
                <Section icon={Calculator} title="5. Régimen tributario" subtitle="Define cómo se calcula el IGV">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="tax_regime">Régimen *</Label>
                            <select
                                id="tax_regime"
                                value={data.tax_regime}
                                onChange={(e) => setData('tax_regime', e.target.value)}
                                className="form-select"
                                required
                            >
                                <option value="general">General (18% IGV)</option>
                                <option value="mype_restaurantes">MYPE Restaurantes (Ley 31556)</option>
                                <option value="nrus">NRUS (solo boletas, 0% IGV)</option>
                            </select>
                        </div>
                        {data.tax_regime !== 'nrus' && (
                            <div>
                                <Label htmlFor="igv_rate_override">IGV override (%)</Label>
                                <Input
                                    id="igv_rate_override"
                                    type="number"
                                    step="0.01"
                                    value={data.igv_rate_override as string}
                                    onChange={(e) => setData('igv_rate_override', e.target.value)}
                                    placeholder="18.00"
                                />
                            </div>
                        )}
                        {data.tax_regime === 'nrus' && (
                            <div>
                                <Label htmlFor="nrus_categoria">Categoría NRUS</Label>
                                <select
                                    id="nrus_categoria"
                                    value={data.nrus_categoria as string}
                                    onChange={(e) => setData('nrus_categoria', e.target.value)}
                                    className="form-select"
                                >
                                    <option value="">—</option>
                                    <option value="1">Cat. 1 (S/20/mes — hasta S/5k)</option>
                                    <option value="2">Cat. 2 (S/50/mes — hasta S/8k)</option>
                                </select>
                            </div>
                        )}
                    </div>
                </Section>

                {/* 6. Emisión: modo + plan */}
                <Section icon={CreditCard} title="6. Emisión y plan" subtitle="Define si la empresa emite ilimitado o según un plan">
                    {emisionGlobalIlimitada && (
                        <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-950/20 dark:text-amber-300">
                            <strong>Emisión ilimitada global activa.</strong>{' '}
                            Mientras el switch global esté encendido, esta empresa emite sin límites sin
                            importar lo que elijas aquí. Este ajuste tomará efecto cuando lo apagues.
                        </div>
                    )}

                    {/* Selector de modo de emisión */}
                    <div className="mb-5">
                        <Label>Modo de emisión *</Label>
                        <div className="mt-2 grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                onClick={() => setData('emission_mode', 'unlimited')}
                                className={`flex items-start gap-3 rounded-lg border p-4 text-left transition-colors ${
                                    data.emission_mode === 'unlimited'
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                        : 'hover:bg-muted/40'
                                }`}
                            >
                                <InfinityIcon className="mt-0.5 size-5 shrink-0 text-primary" />
                                <div>
                                    <div className="font-medium">Ilimitada</div>
                                    <p className="text-muted-foreground text-xs">
                                        Emite sin restricciones. Ideal para programadores e integradores. No
                                        requiere plan.
                                    </p>
                                </div>
                            </button>
                            <button
                                type="button"
                                onClick={() => setData('emission_mode', 'plan')}
                                className={`flex items-start gap-3 rounded-lg border p-4 text-left transition-colors ${
                                    data.emission_mode === 'plan'
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                        : 'hover:bg-muted/40'
                                }`}
                            >
                                <CreditCard className="mt-0.5 size-5 shrink-0 text-primary" />
                                <div>
                                    <div className="font-medium">Por plan / suscripción</div>
                                    <p className="text-muted-foreground text-xs">
                                        Respeta los límites del plan (comprobantes/mes, vencimiento). Ideal para
                                        revendedores y SaaS.
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>

                    {/* Plan y cupo: sólo en modo "por plan" */}
                    {data.emission_mode === 'plan' && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label htmlFor="plan">Plan *</Label>
                                <select
                                    id="plan"
                                    value={data.plan}
                                    onChange={(e) => setData('plan', e.target.value)}
                                    className="form-select"
                                    required
                                >
                                    <option value="free">Free</option>
                                    <option value="pro">Pro</option>
                                    <option value="business">Business</option>
                                    {planes
                                        .filter((p) => !['free', 'pro', 'business'].includes(p.slug))
                                        .map((p) => (
                                            <option key={p.slug} value={p.slug}>
                                                {p.name}
                                            </option>
                                        ))}
                                </select>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    El cupo mensual se toma del plan. Un plan vencido bloquea la emisión.
                                </p>
                            </div>
                            <div>
                                <Label htmlFor="max_documents_month">Documentos SUNAT/mes (referencia)</Label>
                                <Input
                                    id="max_documents_month"
                                    type="number"
                                    value={data.max_documents_month}
                                    onChange={(e) => setData('max_documents_month', Number(e.target.value))}
                                    min={0}
                                    max={99999}
                                    placeholder="Ej: 200 (-1 = ilimitado)"
                                />
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Se sincroniza con el plan al asignarlo.
                                </p>
                            </div>
                        </div>
                    )}

                    {data.emission_mode === 'unlimited' && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-300">
                            <strong>Emisión ilimitada.</strong> Esta empresa emitirá comprobantes sin límite de
                            cantidad ni vencimiento. No se aplicará ningún plan.
                        </div>
                    )}

                    <div className="mt-5 border-t pt-4">
                        <label className="inline-flex items-center gap-2">
                            <Checkbox
                                checked={data.is_active}
                                onCheckedChange={(v) => setData('is_active', v === true)}
                            />
                            <span className="text-sm">Empresa activa</span>
                        </label>
                    </div>
                </Section>

                {/* 7. Comercial */}
                <Section icon={Store} title="7. Comercial" subtitle="Webhook, logo, mensajes y pagos">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <Label htmlFor="webhook_url">Webhook URL</Label>
                            <Input
                                id="webhook_url"
                                type="url"
                                value={data.webhook_url}
                                onChange={(e) => setData('webhook_url', e.target.value)}
                                placeholder="Ej: https://tu-app.com/sunat-webhook (opcional)"
                                maxLength={500}
                            />
                        </div>
                        <div>
                            <Label htmlFor="logo">Logo (jpg/png/webp)</Label>
                            <Input
                                id="logo"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                            />
                            {tenant.has_logo && (
                                <p className="mt-1 text-xs text-emerald-600">✔ Logo actual cargado</p>
                            )}
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="mensaje_agradecimiento">Mensaje de agradecimiento (PDFs)</Label>
                            <textarea
                                id="mensaje_agradecimiento"
                                rows={2}
                                maxLength={500}
                                className="form-input"
                                value={data.mensaje_agradecimiento}
                                onChange={(e) => setData('mensaje_agradecimiento', e.target.value)}
                                placeholder="Ej: Gracias por su compra. Vuelva pronto."
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="mensaje_promocional">Mensaje promocional</Label>
                            <textarea
                                id="mensaje_promocional"
                                rows={2}
                                maxLength={500}
                                className="form-input"
                                value={data.mensaje_promocional}
                                onChange={(e) => setData('mensaje_promocional', e.target.value)}
                                placeholder="Ej: Síguenos en @tuempresa • www.tuempresa.com"
                            />
                        </div>

                        {/* Cuentas bancarias */}
                        <div className="md:col-span-2">
                            <Label>Cuentas bancarias</Label>
                            {data.cuentas_bancarias.map((c, i) => (
                                <div key={i} className="mt-2 grid gap-2 rounded bg-muted/30 p-3 md:grid-cols-6">
                                    <Input
                                        placeholder="Banco"
                                        value={c.banco}
                                        onChange={(e) => {
                                            const arr = [...data.cuentas_bancarias];
                                            arr[i] = { ...arr[i], banco: e.target.value };
                                            setData('cuentas_bancarias', arr);
                                        }}
                                    />
                                    <Input
                                        placeholder="Tipo (ahorros/cte)"
                                        value={c.tipo ?? ''}
                                        onChange={(e) => {
                                            const arr = [...data.cuentas_bancarias];
                                            arr[i] = { ...arr[i], tipo: e.target.value };
                                            setData('cuentas_bancarias', arr);
                                        }}
                                    />
                                    <select
                                        value={c.moneda ?? ''}
                                        onChange={(e) => {
                                            const arr = [...data.cuentas_bancarias];
                                            arr[i] = { ...arr[i], moneda: e.target.value };
                                            setData('cuentas_bancarias', arr);
                                        }}
                                        className="form-select"
                                    >
                                        <option value="">Moneda</option>
                                        <option value="PEN">PEN</option>
                                        <option value="USD">USD</option>
                                    </select>
                                    <Input
                                        placeholder="Número"
                                        value={c.numero}
                                        onChange={(e) => {
                                            const arr = [...data.cuentas_bancarias];
                                            arr[i] = { ...arr[i], numero: e.target.value };
                                            setData('cuentas_bancarias', arr);
                                        }}
                                    />
                                    <Input
                                        placeholder="CCI (opcional)"
                                        value={c.cci ?? ''}
                                        onChange={(e) => {
                                            const arr = [...data.cuentas_bancarias];
                                            arr[i] = { ...arr[i], cci: e.target.value };
                                            setData('cuentas_bancarias', arr);
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => rmRepeat('cuentas_bancarias', i)}
                                        className="text-red-600"
                                    >
                                        Eliminar
                                    </Button>
                                </div>
                            ))}
                            {data.cuentas_bancarias.length < 5 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="mt-2"
                                    onClick={() =>
                                        addRepeat('cuentas_bancarias', {
                                            banco: '',
                                            tipo: '',
                                            moneda: 'PEN',
                                            numero: '',
                                            cci: '',
                                        })
                                    }
                                >
                                    <Plus className="size-3" /> Añadir cuenta
                                </Button>
                            )}
                        </div>

                        {/* Billeteras */}
                        <div className="md:col-span-2">
                            <Label>Billeteras digitales</Label>
                            {data.billeteras_digitales.map((b, i) => (
                                <div key={i} className="mt-2 grid gap-2 rounded bg-muted/30 p-3 md:grid-cols-3">
                                    <Input
                                        placeholder="Ej: Yape / Plin / Tunki..."
                                        value={b.tipo}
                                        onChange={(e) => {
                                            const arr = [...data.billeteras_digitales];
                                            arr[i] = { ...arr[i], tipo: e.target.value };
                                            setData('billeteras_digitales', arr);
                                        }}
                                    />
                                    <Input
                                        placeholder="Número"
                                        value={b.numero}
                                        onChange={(e) => {
                                            const arr = [...data.billeteras_digitales];
                                            arr[i] = { ...arr[i], numero: e.target.value };
                                            setData('billeteras_digitales', arr);
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => rmRepeat('billeteras_digitales', i)}
                                        className="text-red-600"
                                    >
                                        Eliminar
                                    </Button>
                                </div>
                            ))}
                            {data.billeteras_digitales.length < 5 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="mt-2"
                                    onClick={() => addRepeat('billeteras_digitales', { tipo: '', numero: '' })}
                                >
                                    <Plus className="size-3" /> Añadir billetera
                                </Button>
                            )}
                        </div>
                    </div>
                </Section>

                {/* 8. Usuario asignado */}
                <Section icon={UserCircle} title="8. Usuario asignado (opcional)" subtitle="Cliente que administra esta empresa">
                    <div>
                        <Label htmlFor="user_id">Usuario</Label>
                        <select
                            id="user_id"
                            value={data.user_id as string | number}
                            onChange={(e) => setData('user_id', e.target.value)}
                            className="form-select md:max-w-md"
                        >
                            <option value="">— Sin asignar —</option>
                            {usuarios.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name} ({u.email})
                                </option>
                            ))}
                        </select>
                    </div>
                </Section>

                {progress && (
                    <div className="text-xs text-muted-foreground">
                        Subiendo archivos: {progress.percentage}%
                    </div>
                )}

                {/* Errores generales */}
                {Object.keys(errors).length > 0 && (
                    <Card className="border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/20">
                        <p className="mb-2 text-sm font-medium text-red-800 dark:text-red-300">
                            Corrige los siguientes errores:
                        </p>
                        <ul className="list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-400">
                            {Object.entries(errors).map(([key, msg]) => (
                                <li key={key}>
                                    <span className="font-mono text-xs">{key}</span>: {msg as string}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <div className="flex justify-end gap-3 border-t pt-4">
                    <Button variant="ghost" asChild>
                        <Link href="/admin/empresas">Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {editando ? 'Guardar cambios' : 'Registrar empresa'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
