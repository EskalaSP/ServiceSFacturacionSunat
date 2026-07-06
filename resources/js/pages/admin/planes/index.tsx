import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type Plan = {
    id: number;
    slug: string;
    name: string;
    price_monthly: number;
    price_yearly: number | null;
    documents_month: number;
    features_count: number;
    sort_order: number;
    is_active: boolean;
    subscriptions_count: number;
};

type Props = {
    planes: Plan[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Planes', href: '/admin/planes' },
];

const fmt = (n: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);

export default function PlanesIndex({ planes }: Props) {
    const toggle = (id: number) => {
        router.post(`/admin/planes/${id}/toggle`, {}, { preserveScroll: true });
    };

    const eliminar = (id: number, name: string, subs: number) => {
        if (subs > 0) {
            alert(
                `No se puede eliminar "${name}": tiene ${subs} suscripción(es) asociada(s). ` +
                'Desactívalo en su lugar.',
            );
            return;
        }
        if (confirm(`¿Eliminar plan "${name}"?`)) {
            router.delete(`/admin/planes/${id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planes" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <CreditCard className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">Planes</h1>
                            <p className="text-sm text-muted-foreground">
                                {planes.length} planes definidos · {planes.filter((p) => p.is_active).length} activos
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href="/admin/planes/nuevo">
                            <Plus className="size-4" />
                            Nuevo plan
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Orden</th>
                                    <th className="px-4 py-3 text-left font-medium">Slug</th>
                                    <th className="px-4 py-3 text-left font-medium">Nombre</th>
                                    <th className="px-4 py-3 text-left font-medium">Mensual</th>
                                    <th className="px-4 py-3 text-left font-medium">Anual</th>
                                    <th className="px-4 py-3 text-left font-medium">Docs/mes</th>
                                    <th className="px-4 py-3 text-left font-medium">Features</th>
                                    <th className="px-4 py-3 text-left font-medium">Empresas</th>
                                    <th className="px-4 py-3 text-left font-medium">Estado</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {planes.length === 0 ? (
                                    <tr>
                                        <td colSpan={10} className="px-4 py-10 text-center text-muted-foreground">
                                            Aún no hay planes definidos.
                                        </td>
                                    </tr>
                                ) : (
                                    planes.map((p) => (
                                        <tr key={p.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3 text-xs text-muted-foreground">{p.sort_order}</td>
                                            <td className="px-4 py-3 font-mono text-xs">{p.slug}</td>
                                            <td className="px-4 py-3 font-medium">{p.name}</td>
                                            <td className="px-4 py-3">{fmt(p.price_monthly)}</td>
                                            <td className="px-4 py-3">
                                                {p.price_yearly ? fmt(p.price_yearly) : '—'}
                                            </td>
                                            <td className="px-4 py-3 font-mono">
                                                {p.documents_month === -1 ? '∞' : p.documents_month}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                {p.features_count} activas
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                {p.subscriptions_count > 0 ? (
                                                    <span className="font-medium">{p.subscriptions_count}</span>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {p.is_active ? (
                                                    <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300">
                                                        Activo
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">Inactivo</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex items-center gap-1">
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/admin/planes/${p.id}/editar`}>Editar</Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => toggle(p.id)}
                                                        className={
                                                            p.is_active
                                                                ? 'text-amber-600 hover:text-amber-700'
                                                                : 'text-emerald-600 hover:text-emerald-700'
                                                        }
                                                    >
                                                        {p.is_active ? 'Desactivar' : 'Activar'}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700 disabled:opacity-40"
                                                        onClick={() => eliminar(p.id, p.name, p.subscriptions_count)}
                                                        disabled={p.subscriptions_count > 0}
                                                        title={
                                                            p.subscriptions_count > 0
                                                                ? 'No se puede eliminar: hay empresas asociadas'
                                                                : ''
                                                        }
                                                    >
                                                        Eliminar
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
