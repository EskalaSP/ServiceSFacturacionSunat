import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Plus, Search } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type Empresa = {
    id: number;
    ruc: string;
    razon_social: string;
    nombre_comercial: string | null;
    environment: string;
    tax_regime: string | null;
    plan: string;
    is_active: boolean;
    created_at: string | null;
};

type Paginacion<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    empresas: Paginacion<Empresa>;
    filtros: { buscar: string; plan: string; estado: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
];

export default function EmpresasIndex({ empresas, filtros }: Props) {
    const { data, setData, get, processing } = useForm({
        buscar: filtros.buscar || '',
        plan: filtros.plan || '',
        estado: filtros.estado || '',
    });

    const submitFiltros = (e: React.FormEvent) => {
        e.preventDefault();
        get('/admin/empresas', { preserveState: true, preserveScroll: true });
    };

    const toggle = (id: number) => {
        router.post(`/admin/empresas/${id}/toggle`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Empresas" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Building2 className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">Empresas</h1>
                            <p className="text-sm text-muted-foreground">
                                {empresas.total} {empresas.total === 1 ? 'empresa registrada' : 'empresas registradas'}
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href="/admin/empresas/nueva">
                            <Plus className="size-4" />
                            Nueva empresa
                        </Link>
                    </Button>
                </div>

                {/* Filtros */}
                <Card className="p-4">
                    <form onSubmit={submitFiltros} className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <div className="relative sm:col-span-2">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="pl-9"
                                placeholder="Buscar por RUC, razón social..."
                                value={data.buscar}
                                onChange={(e) => setData('buscar', e.target.value)}
                            />
                        </div>
                        <select
                            className="form-select"
                            value={data.plan}
                            onChange={(e) => setData('plan', e.target.value)}
                        >
                            <option value="">Todos los planes</option>
                            <option value="free">Free</option>
                            <option value="pro">Pro</option>
                            <option value="business">Business</option>
                        </select>
                        <div className="flex gap-2">
                            <select
                                className="form-select flex-1"
                                value={data.estado}
                                onChange={(e) => setData('estado', e.target.value)}
                            >
                                <option value="">Todos</option>
                                <option value="activa">Activas</option>
                                <option value="inactiva">Inactivas</option>
                            </select>
                            <Button type="submit" variant="secondary" disabled={processing}>
                                Filtrar
                            </Button>
                        </div>
                    </form>
                </Card>

                {/* Tabla */}
                <Card className="overflow-hidden p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">RUC</th>
                                    <th className="px-4 py-3 text-left font-medium">Razón social</th>
                                    <th className="px-4 py-3 text-left font-medium">Entorno</th>
                                    <th className="px-4 py-3 text-left font-medium">Régimen</th>
                                    <th className="px-4 py-3 text-left font-medium">Plan</th>
                                    <th className="px-4 py-3 text-left font-medium">Estado</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {empresas.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-muted-foreground">
                                            No hay empresas que coincidan con los filtros.
                                        </td>
                                    </tr>
                                ) : (
                                    empresas.data.map((t) => (
                                        <tr key={t.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3 font-mono text-xs">{t.ruc}</td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{t.razon_social}</div>
                                                {t.nombre_comercial && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {t.nombre_comercial}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={t.environment === 'production' ? 'default' : 'secondary'}
                                                    className="uppercase text-[10px]"
                                                >
                                                    {t.environment}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                {t.tax_regime ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline" className="uppercase text-[10px]">
                                                    {t.plan}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                {t.is_active ? (
                                                    <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300">
                                                        Activa
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">Inactiva</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex items-center gap-1">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/admin/empresas/${t.id}`}>Ver</Link>
                                                    </Button>
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/admin/empresas/${t.id}/editar`}>Editar</Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => toggle(t.id)}
                                                        className={
                                                            t.is_active
                                                                ? 'text-amber-600 hover:text-amber-700'
                                                                : 'text-emerald-600 hover:text-emerald-700'
                                                        }
                                                    >
                                                        {t.is_active ? 'Desactivar' : 'Activar'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {empresas.last_page > 1 && (
                        <div className="flex items-center justify-between border-t px-4 py-3 text-xs text-muted-foreground">
                            <div>
                                Mostrando {empresas.from ?? 0}–{empresas.to ?? 0} de {empresas.total}
                            </div>
                            <div className="flex gap-1">
                                {empresas.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            preserveScroll
                                            className={`min-w-[32px] rounded px-2 py-1 text-center ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'hover:bg-muted'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            className="min-w-[32px] rounded px-2 py-1 text-center opacity-40"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
