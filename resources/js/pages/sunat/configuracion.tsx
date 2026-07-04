import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Eye, EyeOff, Loader2, Wifi, WifiOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import SunatLayout from '@/layouts/sunat-layout';
import type { TenantSunat } from '@/types';

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

export default function Configuracion({ tenant }: Props) {
    const [solUser, setSolUser]           = useState(tenant?.sol_user ?? '');
    const [solPass, setSolPass]           = useState('');
    const [showPass, setShowPass]         = useState(false);
    const [environment, setEnvironment]   = useState<'beta' | 'produccion'>(tenant?.environment ?? 'beta');
    const [serieFactura, setSerieFactura] = useState(tenant?.serie_factura ?? 'F001');
    const [serieBoleta, setSerieBoleta]   = useState(tenant?.serie_boleta ?? 'B001');
    const [certificate, setCertificate]   = useState<File | null>(null);
    const [certificatePassword, setCertificatePassword] = useState('');
    const [saving, setSaving]             = useState(false);
    const [testing, setTesting]           = useState(false);
    const [testResult, setTestResult]     = useState<TestResult>(null);
    const [errors, setErrors]             = useState<Record<string, string>>({});

    function validate() {
        const e: Record<string, string> = {};
        if (!solUser.trim()) e.sol_user = 'El usuario SOL es requerido.';
        if (!solPass.trim()) e.sol_pass = 'La clave SOL es requerida.';
        if (!/^F\d{3}$/.test(serieFactura)) e.serie_factura = 'Formato inválido (ej: F001)';
        if (!/^B\d{3}$/.test(serieBoleta))  e.serie_boleta  = 'Formato inválido (ej: B001)';
        setErrors(e);
        return Object.keys(e).length === 0;
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!validate()) return;
        setSaving(true);
        
        const formData = new FormData();
        formData.append('sol_user', solUser);
        formData.append('sol_pass', solPass);
        formData.append('environment', environment);
        formData.append('serie_factura', serieFactura);
        formData.append('serie_boleta', serieBoleta);
        if (certificate) {
            formData.append('certificate', certificate);
        }
        if (certificatePassword) {
            formData.append('certificate_password', certificatePassword);
        }
        formData.append('_method', 'put'); // Inertia requires POST method to upload files, spoofing PUT

        router.post('/sunat/configuracion', formData, { 
            onFinish: () => setSaving(false),
            forceFormData: true 
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
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                    ),
                },
            });
            const data = await res.json();
            setTestResult(data);
        } catch {
            setTestResult({ ok: false, mensaje: 'Error de conexión.' });
        } finally {
            setTesting(false);
        }
    }

    return (
        <SunatLayout>
            <Head title="Configuración SUNAT" />

            <div className="mx-auto max-w-2xl p-4 md:p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Configuración SUNAT</h1>
                    <p className="text-sm text-muted-foreground">
                        Ingresa tus credenciales SOL para conectar con SUNAT.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    {/* Credenciales SOL */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Credenciales SOL</CardTitle>
                            <CardDescription>
                                Usa un usuario SOL secundario, no el principal de tu empresa.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="solUser">Usuario SOL secundario</Label>
                                <Input
                                    id="solUser"
                                    value={solUser}
                                    onChange={e => setSolUser(e.target.value)}
                                    placeholder="ej: EMPRESA20123456"
                                    aria-invalid={!!errors.sol_user}
                                />
                                {errors.sol_user && <p className="text-xs text-destructive">{errors.sol_user}</p>}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="sol_pass">Clave SOL</Label>
                                <div className="relative">
                                    <Input
                                        id="sol_pass"
                                        type={showPass ? 'text' : 'password'}
                                        value={solPass}
                                        onChange={(e) => setSolPass(e.target.value)}
                                        placeholder={tenant?.sol_user ? '••••••••' : 'Ingresa tu clave SOL'}
                                        aria-invalid={!!errors.sol_pass}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPass((v) => !v)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        {showPass ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                                {errors.sol_pass && (
                                    <p className="text-xs text-destructive">{errors.sol_pass}</p>
                                )}
                                {tenant?.sol_user && (
                                    <p className="text-xs text-muted-foreground">
                                        Deja en blanco para mantener la clave actual.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Ambiente */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Ambiente</CardTitle>
                            <CardDescription>
                                Usa Beta para pruebas. Producción para emisión real.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-3">
                                {(['beta', 'produccion'] as const).map((env) => (
                                    <button
                                        key={env}
                                        type="button"
                                        onClick={() => setEnvironment(env)}
                                        className={`flex flex-col items-start rounded-lg border p-4 text-left transition-colors ${
                                            environment === env
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:bg-muted/50'
                                        }`}
                                    >
                                        <span className="text-sm font-medium capitalize">
                                            {env === 'beta' ? 'Beta / Homologación' : 'Producción'}
                                        </span>
                                        <span className="mt-1 text-xs text-muted-foreground">
                                            {env === 'beta'
                                                ? 'Para pruebas — no genera documentos reales'
                                                : 'Emisión real ante SUNAT'}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Certificado Digital */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Certificado Digital</CardTitle>
                            <CardDescription>
                                Sube tu certificado (.p12 o .pfx) para firmar los comprobantes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="certificate">Archivo del Certificado</Label>
                                <Input
                                    id="certificate"
                                    type="file"
                                    accept=".pfx,.p12,.pem"
                                    onChange={e => {
                                        const file = e.target.files?.[0];
                                        if (file) {
                                            setCertificate(file);
                                        } else {
                                            setCertificate(null);
                                        }
                                    }}
                                />
                                <p className="text-xs text-muted-foreground">Opcional si ya subiste uno antes.</p>
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="certPass">Clave del Certificado</Label>
                                <Input
                                    id="certPass"
                                    type="password"
                                    value={certificatePassword}
                                    onChange={e => setCertificatePassword(e.target.value)}
                                    placeholder="Solo si subes un archivo nuevo o cambiaste la clave"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Series */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Series de numeración</CardTitle>
                            <CardDescription>
                                Las series deben iniciar con F para facturas y B para boletas.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="serie_factura">Serie Factura</Label>
                                <Input
                                    id="serie_factura"
                                    value={serieFactura}
                                    onChange={(e) => setSerieFactura(e.target.value.toUpperCase())}
                                    maxLength={4}
                                    placeholder="F001"
                                    aria-invalid={!!errors.serie_factura}
                                />
                                {errors.serie_factura && (
                                    <p className="text-xs text-destructive">{errors.serie_factura}</p>
                                )}
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="serie_boleta">Serie Boleta</Label>
                                <Input
                                    id="serie_boleta"
                                    value={serieBoleta}
                                    onChange={(e) => setSerieBoleta(e.target.value.toUpperCase())}
                                    maxLength={4}
                                    placeholder="B001"
                                    aria-invalid={!!errors.serie_boleta}
                                />
                                {errors.serie_boleta && (
                                    <p className="text-xs text-destructive">{errors.serie_boleta}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Test result */}
                    {testResult && (
                        <div
                            className={`flex items-start gap-3 rounded-lg border p-4 ${
                                testResult.ok
                                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300'
                                    : 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300'
                            }`}
                        >
                            {testResult.ok ? <Wifi className="mt-0.5 size-4 shrink-0" /> : <WifiOff className="mt-0.5 size-4 shrink-0" />}
                            <div className="text-sm">
                                <p className="font-medium">{testResult.mensaje}</p>
                                {testResult.ok && testResult.ambiente && (
                                    <p className="mt-0.5 text-xs opacity-80">
                                        {testResult.ambiente} &middot; RUC {testResult.ruc} &middot; Usuario {testResult.usuario}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={saving}>
                            {saving && <Loader2 className="mr-2 size-4 animate-spin" />}
                            Guardar configuración
                        </Button>
                        <Button type="button" variant="outline" disabled={testing} onClick={probarConexion}>
                            {testing ? (
                                <Loader2 className="mr-2 size-4 animate-spin" />
                            ) : (
                                <Wifi className="mr-2 size-4" />
                            )}
                            Probar conexión
                        </Button>
                    </div>
                </form>
            </div>
        </SunatLayout>
    );
}
