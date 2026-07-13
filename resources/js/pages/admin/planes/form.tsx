import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, Infinity as InfinityIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { BreadcrumbItem } from '@/types';

type LimitDef = { label: string; help?: string };
type Catalogo = {
    limits: Record<string, Record<string, LimitDef>>;   // categoría → { key → def }
    features: Record<string, Record<string, string>>;   // categoría → { key → label }
};

type Plan = {
    id: number | null;
    slug: string;
    name: string;
    price_monthly: number;
    price_yearly: number | null;
    sort_order: number;
    limits: Record<string, number>;
    features: string[];
    is_unlimited: boolean;
    duration_days: number | null;
    is_active: boolean;
    subscriptions_count?: number;
};

type Props = {
    plan: Plan;
    catalogo: Catalogo;
    modo: 'crear' | 'editar';
};

const breadcrumbs = (modo: 'crear' | 'editar', name?: string): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Planes', href: '/admin/planes' },
    { title: modo === 'crear' ? 'Nuevo plan' : `Editar: ${name ?? ''}`, href: '#' },
];

const validKey = (k: string) => /^[a-z0-9_]+$/.test(k);

export default function PlanesForm({ plan, catalogo, modo }: Props) {
    const editando = modo === 'editar';

    const { data, setData, post, put, processing, errors } = useForm({
        slug: plan.slug,
        name: plan.name,
        price_monthly: plan.price_monthly,
        price_yearly: plan.price_yearly ?? '',
        sort_order: plan.sort_order,
        limits: { ...plan.limits } as Record<string, number | ''>,
        features: [...(plan.features ?? [])],
        is_unlimited: plan.is_unlimited ?? false,
        duration_days: (plan.duration_days ?? '') as number | '',
        is_active: plan.is_active,
    });

    // Claves conocidas (planas)
    const conocidasLimits = useMemo(
        () => Object.values(catalogo.limits ?? {}).flatMap((g) => Object.keys(g)),
        [catalogo],
    );
    const conocidasFeatures = useMemo(
        () => Object.values(catalogo.features ?? {}).flatMap((g) => Object.keys(g)),
        [catalogo],
    );

    // Claves personalizadas que ya tiene el plan (no están en el catálogo)
    const limitsCustom = Object.keys(data.limits).filter((k) => !conocidasLimits.includes(k));
    const featuresCustom = data.features.filter((f) => !conocidasFeatures.includes(f));

    const [nuevaLimitKey, setNuevaLimitKey] = useState('');
    const [nuevaLimitValor, setNuevaLimitValor] = useState<number | ''>('');
    const [nuevaFeatureKey, setNuevaFeatureKey] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando && plan.id) put(`/admin/planes/${plan.id}`);
        else post('/admin/planes');
    };

    const toggleFeature = (key: string, checked: boolean) => {
        if (checked) setData('features', [...data.features, key]);
        else setData('features', data.features.filter((f) => f !== key));
    };

    const setLimit = (key: string, valor: number | '') => {
        setData('limits', { ...data.limits, [key]: valor });
    };

    const eliminarLimit = (key: string) => {
        const nuevo = { ...data.limits };
        delete nuevo[key];
        setData('limits', nuevo);
    };

    const agregarLimitCustom = () => {
        const k = nuevaLimitKey.trim().toLowerCase();
        if (!k || !validKey(k)) {
            alert('La clave debe ser snake_case (ej: max_pedidos_mes).');
            return;
        }
        if (k in data.limits) {
            alert('Esa clave ya existe.');
            return;
        }
        setLimit(k, nuevaLimitValor === '' ? -1 : Number(nuevaLimitValor));
        setNuevaLimitKey('');
        setNuevaLimitValor('');
    };

    const agregarFeatureCustom = () => {
        const k = nuevaFeatureKey.trim().toLowerCase();
        if (!k || !validKey(k)) {
            alert('La clave debe ser snake_case (ej: nueva_integracion).');
            return;
        }
        if (data.features.includes(k)) {
            alert('Esa feature ya está en el plan.');
            return;
        }
        setData('features', [...data.features, k]);
        setNuevaFeatureKey('');
    };

    const eliminarFeatureCustom = (key: string) => {
        setData('features', data.features.filter((f) => f !== key));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(modo, plan.name)}>
            <Head title={editando ? 'Editar plan' : 'Nuevo plan'} />

            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                {/* Encabezado */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {editando ? `Editar: ${plan.name}` : 'Nuevo plan'}
                        </h1>
                        {editando && plan.subscriptions_count !== undefined && (
                            <p className="text-sm text-muted-foreground">
                                {plan.subscriptions_count} suscripción(es) activa(s) usan este plan
                            </p>
                        )}
                    </div>
                    <Button variant="ghost" asChild>
                        <Link href="/admin/planes">
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                {/* Identidad y precio */}
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
                                placeholder="pro, business…"
                            />
                            {errors.slug && <p className="mt-1 text-xs text-red-600">{errors.slug}</p>}
                            <p className="mt-1 text-xs text-muted-foreground">
                                Solo minúsculas, números y guiones. No se puede cambiar después.
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
                                placeholder="Ej: Plan Profesional"
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
                                placeholder="49.00"
                            />
                            {errors.price_monthly && (
                                <p className="mt-1 text-xs text-red-600">{errors.price_monthly}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="price_yearly">Precio anual (S/)</Label>
                            <Input
                                id="price_yearly"
                                type="number"
                                step="0.01"
                                value={data.price_yearly}
                                onChange={(e) =>
                                    setData('price_yearly', e.target.value === '' ? '' : Number(e.target.value))
                                }
                                min={0}
                                placeholder="490.00 (opcional)"
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
                                placeholder="0, 1, 2…"
                            />
                        </div>
                    </div>
                </Card>

                {/* Emisión y vigencia */}
                <Card className="p-6">
                    <div className="mb-4">
                        <h3 className="text-base font-semibold">Emisión y vigencia</h3>
                        <p className="text-muted-foreground text-sm">
                            Define si el plan es ilimitado y cuántos días dura la suscripción.
                        </p>
                    </div>

                    <div className="flex items-start justify-between gap-4 rounded-lg border p-4">
                        <div className="flex items-start gap-3">
                            <InfinityIcon className="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <div className="font-medium">Plan ilimitado</div>
                                <p className="text-muted-foreground text-xs">
                                    Las empresas con este plan emiten comprobantes sin límite. Ignora el cupo de
                                    documentos SUNAT de abajo.
                                </p>
                            </div>
                        </div>
                        <Switch
                            checked={data.is_unlimited}
                            onCheckedChange={(v) => setData('is_unlimited', v)}
                        />
                    </div>

                    <div className="mt-4 max-w-xs">
                        <Label htmlFor="duration_days">Vigencia de la suscripción (días)</Label>
                        <Input
                            id="duration_days"
                            type="number"
                            min={1}
                            max={3650}
                            value={data.duration_days}
                            onChange={(e) =>
                                setData('duration_days', e.target.value === '' ? '' : Number(e.target.value))
                            }
                            placeholder="Vacío = sin vencimiento"
                        />
                        {errors.duration_days && (
                            <p className="mt-1 text-xs text-red-600">{errors.duration_days}</p>
                        )}
                        <p className="text-muted-foreground mt-1 text-xs">
                            Ej: 30 (mensual), 365 (anual). Vacío = no vence. Al vencer, la empresa no puede
                            emitir hasta renovar.
                        </p>
                    </div>
                </Card>

                {/* Límites */}
                <Card className={`p-6 ${data.is_unlimited ? 'opacity-60' : ''}`}>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-semibold">Límites de uso</h3>
                            <p className="text-sm text-muted-foreground">
                                Usa <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">-1</code>{' '}
                                para ilimitado. Deja vacío si el límite no aplica a este plan.
                            </p>
                        </div>
                    </div>

                    {Object.entries(catalogo.limits ?? {}).map(([categoria, limits]) => (
                        <div key={categoria} className="mb-6 last:mb-0">
                            <h4 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                {categoria}
                            </h4>
                            <div className="grid gap-4 md:grid-cols-2">
                                {Object.entries(limits).map(([key, def]) => {
                                    const valor = data.limits[key];
                                    const esIlimitado = valor === -1;
                                    return (
                                        <div key={key}>
                                            <div className="flex items-center justify-between">
                                                <Label htmlFor={`limit-${key}`}>{def.label}</Label>
                                                <button
                                                    type="button"
                                                    onClick={() => setLimit(key, esIlimitado ? '' : -1)}
                                                    className={`inline-flex items-center gap-1 text-xs ${
                                                        esIlimitado
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : 'text-muted-foreground hover:text-foreground'
                                                    }`}
                                                >
                                                    <InfinityIcon className="size-3.5" />
                                                    {esIlimitado ? 'Ilimitado' : 'Marcar ilimitado'}
                                                </button>
                                            </div>
                                            <Input
                                                id={`limit-${key}`}
                                                type="number"
                                                className="mt-1 font-mono"
                                                value={valor ?? ''}
                                                onChange={(e) =>
                                                    setLimit(
                                                        key,
                                                        e.target.value === '' ? '' : Number(e.target.value),
                                                    )
                                                }
                                                placeholder="Ej: 500 o -1"
                                                disabled={esIlimitado}
                                            />
                                            {def.help && (
                                                <p className="mt-1 text-xs text-muted-foreground">{def.help}</p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ))}

                    {/* Personalizados */}
                    {limitsCustom.length > 0 && (
                        <div className="mb-6">
                            <h4 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                Personalizados
                            </h4>
                            <div className="space-y-2">
                                {limitsCustom.map((key) => (
                                    <div key={key} className="flex items-center gap-2">
                                        <code className="rounded bg-muted px-2 py-1.5 font-mono text-xs">
                                            {key}
                                        </code>
                                        <Input
                                            type="number"
                                            className="max-w-40 font-mono"
                                            value={data.limits[key] ?? ''}
                                            onChange={(e) =>
                                                setLimit(
                                                    key,
                                                    e.target.value === '' ? '' : Number(e.target.value),
                                                )
                                            }
                                            placeholder="Valor"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => eliminarLimit(key)}
                                            className="text-red-600 hover:text-red-700"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Agregar límite custom */}
                    <div className="rounded-md border border-dashed p-4">
                        <p className="mb-2 text-sm font-medium">Agregar límite personalizado</p>
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="flex-1 min-w-56">
                                <Label htmlFor="new-limit-key" className="text-xs">
                                    Clave (snake_case)
                                </Label>
                                <Input
                                    id="new-limit-key"
                                    value={nuevaLimitKey}
                                    onChange={(e) => setNuevaLimitKey(e.target.value.toLowerCase())}
                                    placeholder="ej: max_pedidos_mes"
                                    className="font-mono"
                                />
                            </div>
                            <div className="min-w-32">
                                <Label htmlFor="new-limit-val" className="text-xs">
                                    Valor
                                </Label>
                                <Input
                                    id="new-limit-val"
                                    type="number"
                                    value={nuevaLimitValor}
                                    onChange={(e) =>
                                        setNuevaLimitValor(e.target.value === '' ? '' : Number(e.target.value))
                                    }
                                    placeholder="-1 = ilim."
                                    className="font-mono"
                                />
                            </div>
                            <Button type="button" onClick={agregarLimitCustom} variant="secondary">
                                <Plus className="size-4" />
                                Agregar
                            </Button>
                        </div>
                    </div>
                </Card>

                {/* Features */}
                <Card className="p-6">
                    <div className="mb-4">
                        <h3 className="text-base font-semibold">Características incluidas</h3>
                        <p className="text-sm text-muted-foreground">
                            Marca las funciones que este plan desbloquea.
                        </p>
                    </div>

                    {Object.entries(catalogo.features ?? {}).map(([categoria, features]) => (
                        <div key={categoria} className="mb-6 last:mb-0">
                            <h4 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                {categoria}
                            </h4>
                            <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                                {Object.entries(features).map(([key, label]) => (
                                    <label
                                        key={key}
                                        className={`flex cursor-pointer items-start gap-2 rounded-md border p-2.5 text-sm transition-colors ${
                                            data.features.includes(key)
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:bg-muted/40'
                                        }`}
                                    >
                                        <Checkbox
                                            checked={data.features.includes(key)}
                                            onCheckedChange={(v) => toggleFeature(key, v === true)}
                                        />
                                        <div className="flex-1">
                                            <div>{label}</div>
                                            <code className="text-xs text-muted-foreground">{key}</code>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}

                    {/* Features personalizadas */}
                    {featuresCustom.length > 0 && (
                        <div className="mb-6">
                            <h4 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                Personalizadas
                            </h4>
                            <div className="flex flex-wrap gap-2">
                                {featuresCustom.map((key) => (
                                    <div
                                        key={key}
                                        className="inline-flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 dark:border-amber-700/50 dark:bg-amber-900/20"
                                    >
                                        <code className="font-mono text-xs">{key}</code>
                                        <button
                                            type="button"
                                            onClick={() => eliminarFeatureCustom(key)}
                                            className="text-red-600 hover:text-red-700"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Agregar feature custom */}
                    <div className="rounded-md border border-dashed p-4">
                        <p className="mb-2 text-sm font-medium">Agregar característica personalizada</p>
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="flex-1 min-w-56">
                                <Label htmlFor="new-feature-key" className="text-xs">
                                    Clave (snake_case)
                                </Label>
                                <Input
                                    id="new-feature-key"
                                    value={nuevaFeatureKey}
                                    onChange={(e) => setNuevaFeatureKey(e.target.value.toLowerCase())}
                                    placeholder="ej: nueva_integracion"
                                    className="font-mono"
                                />
                            </div>
                            <Button type="button" onClick={agregarFeatureCustom} variant="secondary">
                                <Plus className="size-4" />
                                Agregar
                            </Button>
                        </div>
                    </div>
                </Card>

                {/* Estado */}
                <Card className="p-6">
                    <label className="inline-flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.is_active}
                            onCheckedChange={(v) => setData('is_active', v === true)}
                        />
                        Plan activo (asignable a nuevas empresas)
                    </label>
                </Card>

                <div className="sticky bottom-0 -mx-4 flex justify-end gap-3 border-t bg-background/80 px-4 py-3 backdrop-blur">
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
