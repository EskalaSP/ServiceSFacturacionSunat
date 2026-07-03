import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type Plan = {
    id: number | null;
    slug: string;
    name: string;
    price_monthly: number;
    price_yearly: number | null;
    sort_order: number;
    limits: Record<string, number>;
    features: string[];
    is_active: boolean;
};

type Props = {
    plan: Plan;
    limitKeys: string[];
    featureKeys: string[];
    modo: 'crear' | 'editar';
};

const LIMIT_LABELS: Record<string, string> = {
    documents_month: 'Documentos SUNAT / mes',
    sucursales: 'Sucursales',
    team: 'Miembros del equipo',
    productos: 'Productos',
    ai_messages: 'Mensajes de IA / mes',
};

const FEATURE_LABELS: Record<string, string> = {
    sunat: 'Emisión SUNAT (facturas/boletas/NC/ND)',
    boletas: 'Boletas + resumen diario',
    notas: 'Notas de crédito/débito',
    guias: 'Guías de remisión (GRR/GRT)',
    retenciones: 'Retenciones',
    percepciones: 'Percepciones',
    sire: 'SIRE (Registro de Compras)',
    webhooks: 'Webhooks',
    panel: 'Panel de control',
    reportes: 'Reportes avanzados',
    export_zip: 'Exportación masiva ZIP',
    ai_assistant: 'Asistente IA',
};

const breadcrumbs = (modo: 'crear' | 'editar', name?: string): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Planes', href: '/admin/planes' },
    { title: modo === 'crear' ? 'Nuevo plan' : `Editar: ${name ?? ''}`, href: '#' },
];

export default function PlanesForm({ plan, limitKeys, featureKeys, modo }: Props) {
    const editando = modo === 'editar';
    const { data, setData, post, put, processing, errors } = useForm({
        slug: plan.slug,
        name: plan.name,
        price_monthly: plan.price_monthly,
        price_yearly: plan.price_yearly ?? '',
        sort_order: plan.sort_order,
        limits: { ...plan.limits } as Record<string, number | string>,
        features: [...(plan.features ?? [])],
        is_active: plan.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando && plan.id) put(`/admin/planes/${plan.id}`);
        else post('/admin/planes');
    };

    const toggleFeature = (key: string, checked: boolean) => {
        if (checked) setData('features', [...data.features, key]);
        else setData('features', data.features.filter((f) => f !== key));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(modo, plan.name)}>
            <Head title={editando ? 'Editar plan' : 'Nuevo plan'} />

            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {editando ? `Editar: ${plan.name}` : 'Nuevo plan'}
                        </h1>
                    </div>
                    <Button variant="ghost" asChild>
                        <Link href="/admin/planes">
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                <Card className="p-6">
                    <h3 className="mb-4 text-base font-semibold">Identidad y precio</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="slug">Slug *</Label>
                            <Input
                                id="slug"
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value.toLowerCase())}
                                required
                                pattern="[a-z0-9_-]+"
                                readOnly={editando}
                                className={editando ? 'bg-muted font-mono' : 'font-mono'}
                                placeholder="pro, business..."
                            />
                            {errors.slug && <p className="mt-1 text-xs text-red-600">{errors.slug}</p>}
                            <p className="mt-1 text-xs text-muted-foreground">
                                Solo minúsculas, números, guiones.
                            </p>
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="name">Nombre visible *</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                maxLength={100}
                                placeholder="Ej: Plan Pro"
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <Label htmlFor="price_monthly">Precio mensual (S/) *</Label>
                            <Input
                                id="price_monthly"
                                type="number"
                                step="0.01"
                                value={data.price_monthly}
                                onChange={(e) => setData('price_monthly', Number(e.target.value))}
                                required
                                min={0}
                                placeholder="29.00"
                            />
                        </div>
                        <div>
                            <Label htmlFor="price_yearly">Precio anual (S/)</Label>
                            <Input
                                id="price_yearly"
                                type="number"
                                step="0.01"
                                value={data.price_yearly}
                                onChange={(e) => setData('price_yearly', e.target.value === '' ? '' : Number(e.target.value))}
                                min={0}
                                placeholder="290.00 (opcional)"
                            />
                        </div>
                        <div>
                            <Label htmlFor="sort_order">Orden en el listado</Label>
                            <Input
                                id="sort_order"
                                type="number"
                                value={data.sort_order}
                                onChange={(e) => setData('sort_order', Number(e.target.value))}
                                min={0}
                                placeholder="1, 2, 3..."
                            />
                        </div>
                    </div>
                </Card>

                <Card className="p-6">
                    <h3 className="text-base font-semibold">Límites</h3>
                    <p className="mb-4 text-sm text-muted-foreground">
                        Usa <code>-1</code> para ilimitado. Deja vacío si no aplica.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2">
                        {limitKeys.map((key) => (
                            <div key={key}>
                                <Label htmlFor={`limit-${key}`}>{LIMIT_LABELS[key] ?? key}</Label>
                                <Input
                                    id={`limit-${key}`}
                                    type="number"
                                    className="font-mono"
                                    value={(data.limits[key] ?? '') as string}
                                    onChange={(e) =>
                                        setData('limits', { ...data.limits, [key]: e.target.value === '' ? '' : Number(e.target.value) })
                                    }
                                    placeholder="Ej: 200 o -1"
                                />
                            </div>
                        ))}
                    </div>
                </Card>

                <Card className="p-6">
                    <h3 className="mb-4 text-base font-semibold">Features incluidos</h3>
                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        {featureKeys.map((key) => (
                            <label
                                key={key}
                                className="flex items-start gap-2 rounded-md border p-3 hover:bg-muted/40"
                            >
                                <Checkbox
                                    checked={data.features.includes(key)}
                                    onCheckedChange={(v) => toggleFeature(key, v === true)}
                                />
                                <span className="text-sm">{FEATURE_LABELS[key] ?? key}</span>
                            </label>
                        ))}
                    </div>
                </Card>

                <Card className="p-6">
                    <label className="inline-flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.is_active}
                            onCheckedChange={(v) => setData('is_active', v === true)}
                        />
                        Plan activo (visible en el listado público de planes)
                    </label>
                </Card>

                <div className="flex justify-end gap-3 border-t pt-4">
                    <Button variant="ghost" asChild>
                        <Link href="/admin/planes">Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {editando ? 'Guardar cambios' : 'Crear plan'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
