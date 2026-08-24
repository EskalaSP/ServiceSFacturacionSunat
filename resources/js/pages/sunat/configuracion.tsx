import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    FileText,
    IdCard,
    Loader2,
    MapPin,
    Plus,
    Search,
    ShieldCheck,
    Store,
    Wifi,
    WifiOff,
    X,
    type LucideIcon,
} from 'lucide-react';
import SunatLayout from '@/layouts/sunat-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordInput } from '@/components/ui/password-input';
import type { TenantSunat } from '@/types/sunat';

type Props = {
    tenant: TenantSunat | null;
};

type TestResult = {
    ok: boolean;
    mensaje: string;
    ambiente?: string;
    ruc?: string;
    usuario?: string;
} | null;

/** Marca de campo obligatorio: (*) en rojo, bien visible. */
const Req = () => <span className="font-bold text-[#EF233C]">(*)</span>;

const Section = ({
    icon: Icon,
    title,
    subtitle,
    required,
    children,
}: {
    icon: LucideIcon;
    title: string;
    subtitle?: string;
    required?: boolean;
    children: React.ReactNode;
}) => (
    <Card className="p-6">
        <div className="mb-5 flex items-start gap-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="size-5" />
            </div>
            <div className="flex-1">
                <h3 className="text-base font-semibold leading-tight">
                    {title} {required && <Req />}
                </h3>
                {subtitle && <p className="mt-0.5 text-sm text-muted-foreground">{subtitle}</p>}
            </div>
        </div>
        {children}
    </Card>
);

export default function Configuracion({ tenant }: Props) {
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    // Token de consulta y "probar conexión" viven fuera del form principal.
    const [consultaToken, setConsultaToken] = useState('');
    const [savingToken, setSavingToken] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<TestResult>(null);

    const { data, setData, post, put, transform, processing, errors, progress } = useForm({
        // Identidad
        razon_social: tenant?.razon_social ?? '',
        nombre_comercial: tenant?.nombre_comercial ?? '',
        // Ubicación
        direccion: tenant?.direccion ?? '',
        ubigeo: tenant?.ubigeo ?? '',
        departamento: tenant?.departamento ?? '',
        provincia: tenant?.provincia ?? '',
        distrito: tenant?.distrito ?? '',
        telefonos: (tenant?.telefonos && tenant.telefonos.length > 0) ? tenant.telefonos : [''],
        emails: (tenant?.emails && tenant.emails.length > 0) ? tenant.emails : [''],
        // Credenciales SUNAT
        sol_user: tenant?.sol_user ?? '',
        sol_pass: '',
        environment: tenant?.environment ?? 'beta',
        certificate: null as File | null,
        certificate_password: '',
        // Series
        serie_factura: tenant?.serie_factura ?? 'F001',
        serie_boleta: tenant?.serie_boleta ?? 'B001',
        // Comercial
        logo: null as File | null,
        mensaje_agradecimiento: tenant?.mensaje_agradecimiento ?? '',
        mensaje_promocional: tenant?.mensaje_promocional ?? '',
        cuentas_bancarias: tenant?.cuentas_bancarias ?? [],
        billeteras_digitales: tenant?.billeteras_digitales ?? [],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const hayArchivos = data.certificate instanceof File || data.logo instanceof File;

        if (hayArchivos) {
            // Inertia + PUT no envía archivos: se usa POST con method spoofing.
            transform((d) => ({ ...d, _method: 'put' }));
            post('/sunat/configuracion', { forceFormData: true });
        } else {
            transform((d) => d);
            put('/sunat/configuracion');
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

    function guardarToken() {
        setSavingToken(true);
        router.put('/sunat/configuracion/consulta-token', { consulta_token: consultaToken }, {
            preserveScroll: true,
            onSuccess: () => setConsultaToken(''),
            onFinish: () => setSavingToken(false),
        });
    }

    async function probarConexion() {
        setTesting(true);
        setTestResult(null);
        try {
            const res = await fetch('/sunat/configuracion/probar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
                },
            });
            setTestResult(await res.json());
        } catch {
            setTestResult({ ok: false, mensaje: 'Error de conexión.' });
        } finally {
            setTesting(false);
        }
    }

    return (
        <SunatLayout>
            <Head title="Configuración" />

            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">Configuración de mi empresa</h1>
                    <p className="text-sm text-muted-foreground">
                        Actualiza todos los datos de tu empresa, credenciales SOL y opciones de emisión.
                    </p>
                </div>

                {/* 1. Identidad */}
                <Section icon={IdCard} title="1. Identidad" required subtitle="RUC y datos legales de la empresa">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="ruc">RUC</Label>
                            <Input id="ruc" value={tenant?.ruc ?? ''} readOnly className="bg-muted" />
                            <p className="mt-1 text-xs text-muted-foreground">Tu identidad fiscal. No se puede cambiar aquí.</p>
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="razon_social">Razón social <Req /></Label>
                            <Input
                                id="razon_social"
                                value={data.razon_social}
                                onChange={(e) => setData('razon_social', e.target.value)}
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
                                onChange={(e) => setData('ubigeo', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                maxLength={6}
                                placeholder="150101"
                            />
                            {errors.ubigeo && <p className="mt-1 text-xs text-red-600">{errors.ubigeo}</p>}
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="departamento">Departamento</Label>
                            <Input id="departamento" value={data.departamento} onChange={(e) => setData('departamento', e.target.value)} placeholder="LIMA" />
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="provincia">Provincia</Label>
                            <Input id="provincia" value={data.provincia} onChange={(e) => setData('provincia', e.target.value)} placeholder="LIMA" />
                        </div>
                        <div>
                            <Label htmlFor="distrito">Distrito</Label>
                            <Input id="distrito" value={data.distrito} onChange={(e) => setData('distrito', e.target.value)} placeholder="SAN ISIDRO" />
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
                                    <Button type="button" variant="ghost" size="icon" onClick={() => rmRepeat('telefonos', i)}>
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            {data.telefonos.length < 5 && (
                                <Button type="button" variant="ghost" size="sm" className="mt-2" onClick={() => addRepeat('telefonos', '')}>
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
                                    <Button type="button" variant="ghost" size="icon" onClick={() => rmRepeat('emails', i)}>
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            {data.emails.length < 5 && (
                                <Button type="button" variant="ghost" size="sm" className="mt-2" onClick={() => addRepeat('emails', '')}>
                                    <Plus className="size-3" /> Añadir email
                                </Button>
                            )}
                        </div>
                    </div>
                </Section>

                {/* 3. Credenciales SUNAT */}
                <Section icon={ShieldCheck} title="3. Credenciales SUNAT" required subtitle="Usuario secundario, certificado y entorno">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="sol_user">Usuario secundario <Req /></Label>
                            <Input
                                id="sol_user"
                                value={data.sol_user}
                                onChange={(e) => setData('sol_user', e.target.value)}
                                maxLength={20}
                                placeholder="MODDATOS (para beta)"
                            />
                            {errors.sol_user && <p className="mt-1 text-xs text-red-600">{errors.sol_user}</p>}
                        </div>
                        <div>
                            <Label htmlFor="sol_pass">Clave del usuario secundario {!tenant?.sol_user && <Req />}</Label>
                            <PasswordInput
                                id="sol_pass"
                                value={data.sol_pass}
                                onChange={(e) => setData('sol_pass', e.target.value)}
                                placeholder={tenant?.sol_user ? '••••• (dejar vacío = sin cambios)' : 'MODDATOS (para beta)'}
                            />
                            {errors.sol_pass && <p className="mt-1 text-xs text-red-600">{errors.sol_pass}</p>}
                        </div>
                        <div>
                            <Label htmlFor="environment">Entorno <Req /></Label>
                            <Combobox
                                value={data.environment}
                                onChange={(v) => setData('environment', v as 'beta' | 'produccion')}
                                options={[
                                    { value: 'beta', label: 'Beta (pruebas)' },
                                    { value: 'produccion', label: 'Producción' },
                                ]}
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="certificate">Certificado digital (.pfx / .p12 / .pem)</Label>
                            <Input
                                id="certificate"
                                type="file"
                                accept=".pfx,.p12,.pem"
                                onChange={(e) => setData('certificate', e.target.files?.[0] ?? null)}
                            />
                            {errors.certificate && <p className="mt-1 text-xs text-red-600">{errors.certificate}</p>}
                            <p className="mt-1 text-xs text-muted-foreground">Opcional si ya subiste uno antes.</p>
                        </div>
                        <div>
                            <Label htmlFor="certificate_password">Contraseña del certificado</Label>
                            <PasswordInput
                                id="certificate_password"
                                value={data.certificate_password}
                                onChange={(e) => setData('certificate_password', e.target.value)}
                                placeholder="Solo si subes un archivo nuevo o cambiaste la clave"
                            />
                        </div>
                    </div>
                </Section>

                {/* 4. Series de numeración */}
                <Section icon={FileText} title="4. Series de numeración" required subtitle="Deben iniciar con F para facturas y B para boletas">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label htmlFor="serie_factura">Serie Factura <Req /></Label>
                            <Input
                                id="serie_factura"
                                value={data.serie_factura}
                                onChange={(e) => setData('serie_factura', e.target.value.toUpperCase())}
                                maxLength={4}
                                placeholder="F001"
                            />
                            {errors.serie_factura && <p className="mt-1 text-xs text-red-600">{errors.serie_factura}</p>}
                        </div>
                        <div>
                            <Label htmlFor="serie_boleta">Serie Boleta <Req /></Label>
                            <Input
                                id="serie_boleta"
                                value={data.serie_boleta}
                                onChange={(e) => setData('serie_boleta', e.target.value.toUpperCase())}
                                maxLength={4}
                                placeholder="B001"
                            />
                            {errors.serie_boleta && <p className="mt-1 text-xs text-red-600">{errors.serie_boleta}</p>}
                        </div>
                    </div>
                </Section>

                {/* 5. Comercial */}
                <Section icon={Store} title="5. Comercial" subtitle="Logo, mensajes y datos de pago del comprobante">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <Label htmlFor="logo">Logo (jpg/png/webp)</Label>
                            <div className="flex items-start gap-4">
                                <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted/40">
                                    {logoPreview ? (
                                        <img src={logoPreview} alt="Vista previa del logo" className="size-full object-contain p-1" />
                                    ) : tenant?.logo_url ? (
                                        <img src={tenant.logo_url} alt="Logo actual" className="size-full object-contain p-1" onError={(e) => (e.currentTarget.style.display = 'none')} />
                                    ) : (
                                        <Store className="size-7 text-muted-foreground/40" />
                                    )}
                                </div>
                                <div className="flex-1">
                                    <Input
                                        id="logo"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0] ?? null;
                                            setData('logo', file);
                                            if (logoPreview) URL.revokeObjectURL(logoPreview);
                                            setLogoPreview(file ? URL.createObjectURL(file) : null);
                                        }}
                                    />
                                    <p className="mt-1.5 text-xs text-muted-foreground">
                                        Cuadrado <strong>300×300 px</strong>, PNG. Máx. 2 MB.
                                    </p>
                                    {errors.logo && <p className="mt-1 text-xs text-red-600">{errors.logo}</p>}
                                </div>
                            </div>
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
                                    <Input placeholder="Banco" value={c.banco} onChange={(e) => { const arr = [...data.cuentas_bancarias]; arr[i] = { ...arr[i], banco: e.target.value }; setData('cuentas_bancarias', arr); }} />
                                    <Input placeholder="Tipo (ahorros/cte)" value={c.tipo ?? ''} onChange={(e) => { const arr = [...data.cuentas_bancarias]; arr[i] = { ...arr[i], tipo: e.target.value }; setData('cuentas_bancarias', arr); }} />
                                    <Combobox
                                        value={c.moneda ?? ''}
                                        onChange={(v) => { const arr = [...data.cuentas_bancarias]; arr[i] = { ...arr[i], moneda: v }; setData('cuentas_bancarias', arr); }}
                                        options={[{ value: '', label: 'Moneda' }, { value: 'PEN', label: 'PEN' }, { value: 'USD', label: 'USD' }]}
                                        placeholder="Moneda"
                                    />
                                    <Input placeholder="Número" value={c.numero} onChange={(e) => { const arr = [...data.cuentas_bancarias]; arr[i] = { ...arr[i], numero: e.target.value }; setData('cuentas_bancarias', arr); }} />
                                    <Input placeholder="CCI (opcional)" value={c.cci ?? ''} onChange={(e) => { const arr = [...data.cuentas_bancarias]; arr[i] = { ...arr[i], cci: e.target.value }; setData('cuentas_bancarias', arr); }} />
                                    <Button type="button" variant="ghost" size="sm" onClick={() => rmRepeat('cuentas_bancarias', i)} className="text-red-600">Eliminar</Button>
                                </div>
                            ))}
                            {data.cuentas_bancarias.length < 5 && (
                                <Button type="button" variant="ghost" size="sm" className="mt-2" onClick={() => addRepeat('cuentas_bancarias', { banco: '', tipo: '', moneda: 'PEN', numero: '', cci: '' })}>
                                    <Plus className="size-3" /> Añadir cuenta
                                </Button>
                            )}
                        </div>

                        {/* Billeteras */}
                        <div className="md:col-span-2">
                            <Label>Billeteras digitales</Label>
                            {data.billeteras_digitales.map((b, i) => (
                                <div key={i} className="mt-2 grid gap-2 rounded bg-muted/30 p-3 md:grid-cols-3">
                                    <Input placeholder="Ej: Yape / Plin / Tunki..." value={b.tipo} onChange={(e) => { const arr = [...data.billeteras_digitales]; arr[i] = { ...arr[i], tipo: e.target.value }; setData('billeteras_digitales', arr); }} />
                                    <Input placeholder="Número" value={b.numero} onChange={(e) => { const arr = [...data.billeteras_digitales]; arr[i] = { ...arr[i], numero: e.target.value }; setData('billeteras_digitales', arr); }} />
                                    <Button type="button" variant="ghost" size="sm" onClick={() => rmRepeat('billeteras_digitales', i)} className="text-red-600">Eliminar</Button>
                                </div>
                            ))}
                            {data.billeteras_digitales.length < 5 && (
                                <Button type="button" variant="ghost" size="sm" className="mt-2" onClick={() => addRepeat('billeteras_digitales', { tipo: '', numero: '' })}>
                                    <Plus className="size-3" /> Añadir billetera
                                </Button>
                            )}
                        </div>
                    </div>
                </Section>

                {/* 6. Consulta RUC / DNI */}
                <Section icon={Search} title="6. Consulta RUC / DNI" subtitle="Tu propio token de api.json.pe para autocompletar clientes">
                    <div className="grid gap-3 md:max-w-xl">
                        <div>
                            <Label htmlFor="consulta_token">Token de consulta</Label>
                            <PasswordInput
                                id="consulta_token"
                                value={consultaToken}
                                onChange={(e) => setConsultaToken(e.target.value)}
                                placeholder={tenant?.consulta_token_set ? '•••••••• (token configurado)' : 'Pega aquí tu token de api.json.pe'}
                                autoComplete="off"
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                {tenant?.consulta_token_set
                                    ? 'Ya tienes un token guardado. Escribe uno nuevo para reemplazarlo, o deja vacío y guarda para eliminarlo.'
                                    : 'Al buscar un documento, primero se revisa tu base de datos; si no está, se consulta con este token. Cada empresa usa su propio token.'}
                            </p>
                        </div>
                        <div>
                            <Button type="button" variant="outline" disabled={savingToken} onClick={guardarToken}>
                                {savingToken && <Loader2 className="mr-2 size-4 animate-spin" />}
                                Guardar token
                            </Button>
                        </div>
                    </div>
                </Section>

                {/* Resultado de "probar conexión" */}
                {testResult && (
                    <div className={`flex items-start gap-3 rounded-lg border p-4 ${testResult.ok
                        ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300'
                        : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300'}`}>
                        {testResult.ok ? <Wifi className="mt-0.5 size-4 shrink-0" /> : <WifiOff className="mt-0.5 size-4 shrink-0" />}
                        <div className="text-sm">
                            <p className="font-medium">{testResult.mensaje}</p>
                            {testResult.ok && testResult.ambiente && (
                                <p className="mt-0.5 text-xs opacity-80">{testResult.ambiente} &middot; RUC {testResult.ruc} &middot; Usuario {testResult.usuario}</p>
                            )}
                        </div>
                    </div>
                )}

                {progress && (
                    <div className="text-xs text-muted-foreground">Subiendo archivos: {progress.percentage}%</div>
                )}

                {/* Errores generales */}
                {Object.keys(errors).length > 0 && (
                    <Card className="border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/20">
                        <p className="mb-2 text-sm font-medium text-red-800 dark:text-red-300">Corrige los siguientes errores:</p>
                        <ul className="list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-400">
                            {Object.entries(errors).map(([key, msg]) => (
                                <li key={key}><span className="font-mono text-xs">{key}</span>: {msg as string}</li>
                            ))}
                        </ul>
                    </Card>
                )}

                <div className="flex flex-wrap justify-end gap-3 border-t pt-4">
                    <Button type="button" variant="outline" disabled={testing} onClick={probarConexion}>
                        {testing ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Wifi className="mr-2 size-4" />}
                        Probar conexión
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Loader2 className="mr-2 size-4 animate-spin" />}
                        Guardar configuración
                    </Button>
                </div>
            </form>
        </SunatLayout>
    );
}
