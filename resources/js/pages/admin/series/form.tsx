import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type Serie = {
    id: number | null;
    tipo_documento: string;
    serie: string;
    correlativo: number;
    sucursal_id: number | null;
    is_active: boolean;
};

type Props = {
    tenant: { id: number; ruc: string; razon_social: string };
    serie: Serie;
    sucursales: { id: number; nombre: string; cod_local: string }[];
    prefijos: Record<string, string[]>;
    modo: 'crear' | 'editar';
};

const TIPO_LABEL: Record<string, string> = {
    '01': 'Factura (01) — F',
    '03': 'Boleta (03) — B',
    '07': 'Nota de Crédito (07) — F/B',
    '08': 'Nota de Débito (08) — F/B',
    '09': 'Guía Remitente (09) — T',
    '31': 'Guía Transportista (31) — V',
    '20': 'Retención (20) — R',
    '40': 'Percepción (40) — P',
};

const breadcrumbs = (razon: string, id: number, modo: 'crear' | 'editar'): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: razon, href: `/admin/empresas/${id}` },
    { title: 'Series', href: `/admin/empresas/${id}/series` },
    { title: modo === 'crear' ? 'Nueva' : 'Editar', href: '#' },
];

export default function SeriesForm({ tenant, serie, sucursales, prefijos, modo }: Props) {
    const editando = modo === 'editar';
    const { data, setData, post, put, processing, errors } = useForm({
        tipo_documento: serie.tipo_documento,
        serie: serie.serie,
        correlativo: serie.correlativo,
        sucursal_id: serie.sucursal_id ?? '',
        is_active: serie.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando && serie.id) put(`/admin/empresas/${tenant.id}/series/${serie.id}`);
        else post(`/admin/empresas/${tenant.id}/series`);
    };

    const prefijosValidos = prefijos[data.tipo_documento] ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social, tenant.id, modo)}>
            <Head title={editando ? 'Editar serie' : 'Nueva serie'} />

            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {editando ? 'Editar serie' : 'Nueva serie'}
                        </h1>
                        <p className="text-sm text-muted-foreground">{tenant.razon_social}</p>
                    </div>
                    <Button variant="ghost" asChild>
                        <Link href={`/admin/empresas/${tenant.id}/series`}>
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                <Card className="max-w-2xl p-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label htmlFor="tipo_documento">Tipo de documento *</Label>
                            <select
                                id="tipo_documento"
                                value={data.tipo_documento}
                                onChange={(e) => setData('tipo_documento', e.target.value)}
                                disabled={editando}
                                required
                                className={`form-select ${editando ? 'opacity-70' : ''}`}
                            >
                                {Object.entries(TIPO_LABEL).map(([code, label]) => (
                                    <option key={code} value={code}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            {editando && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    El tipo no se puede cambiar en una serie existente.
                                </p>
                            )}
                        </div>

                        <div>
                            <Label htmlFor="serie">Serie *</Label>
                            <Input
                                id="serie"
                                value={data.serie}
                                onChange={(e) => setData('serie', e.target.value.toUpperCase())}
                                required
                                maxLength={4}
                                minLength={4}
                                pattern="[A-Z][A-Z0-9]{3}"
                                readOnly={editando}
                                className={`font-mono uppercase ${editando ? 'bg-muted' : ''}`}
                                placeholder="F001"
                            />
                            {errors.serie && <p className="mt-1 text-xs text-red-600">{errors.serie}</p>}
                            <p className="mt-1 text-xs text-muted-foreground">
                                Prefijo válido: {prefijosValidos.join(' o ')}
                            </p>
                        </div>

                        <div>
                            <Label htmlFor="correlativo">Correlativo actual</Label>
                            <Input
                                id="correlativo"
                                type="number"
                                value={data.correlativo}
                                onChange={(e) => setData('correlativo', Number(e.target.value))}
                                min={0}
                                className="font-mono"
                                placeholder="0"
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Próximo doc será correlativo + 1.
                            </p>
                        </div>

                        <div>
                            <Label htmlFor="sucursal_id">Sucursal</Label>
                            <select
                                id="sucursal_id"
                                value={data.sucursal_id as string | number}
                                onChange={(e) => setData('sucursal_id', e.target.value)}
                                className="form-select"
                            >
                                <option value="">— Sin asignar —</option>
                                {sucursales.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.nombre} ({s.cod_local})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <label className="md:col-span-2 inline-flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.is_active}
                                onCheckedChange={(v) => setData('is_active', v === true)}
                            />
                            Serie activa
                        </label>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t pt-4">
                        <Button variant="ghost" asChild>
                            <Link href={`/admin/empresas/${tenant.id}/series`}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear serie'}
                        </Button>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
