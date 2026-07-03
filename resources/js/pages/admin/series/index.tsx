import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type Serie = {
    id: number;
    tipo_documento: string;
    tipo_nombre: string;
    serie: string;
    correlativo: number;
    sucursal_nombre: string | null;
    is_active: boolean;
};

type Props = {
    tenant: { id: number; ruc: string; razon_social: string };
    series: Serie[];
};

const breadcrumbs = (razon: string, id: number): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: razon, href: `/admin/empresas/${id}` },
    { title: 'Series', href: '#' },
];

export default function SeriesIndex({ tenant, series }: Props) {
    const toggle = (id: number) => {
        router.post(`/admin/empresas/${tenant.id}/series/${id}/toggle`, {}, { preserveScroll: true });
    };

    const eliminar = (id: number) => {
        if (confirm('¿Eliminar serie? Esto no borra los documentos ya emitidos.')) {
            router.delete(`/admin/empresas/${tenant.id}/series/${id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social, tenant.id)}>
            <Head title={`Series — ${tenant.razon_social}`} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Series</h1>
                        <p className="text-sm text-muted-foreground">{tenant.razon_social}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={`/admin/empresas/${tenant.id}`}>
                                <ArrowLeft className="size-4" />
                                Volver a empresa
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={`/admin/empresas/${tenant.id}/series/nueva`}>
                                <Plus className="size-4" />
                                Nueva serie
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card className="overflow-hidden p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Tipo</th>
                                    <th className="px-4 py-3 text-left font-medium">Serie</th>
                                    <th className="px-4 py-3 text-left font-medium">Correlativo</th>
                                    <th className="px-4 py-3 text-left font-medium">Sucursal</th>
                                    <th className="px-4 py-3 text-left font-medium">Estado</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {series.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-10 text-center text-muted-foreground">
                                            Aún no hay series. Crea al menos F001 (facturas) y B001 (boletas).
                                        </td>
                                    </tr>
                                ) : (
                                    series.map((s) => (
                                        <tr key={s.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <span className="text-xs uppercase font-medium">{s.tipo_nombre}</span>
                                                <span className="ml-1 text-xs text-muted-foreground">({s.tipo_documento})</span>
                                            </td>
                                            <td className="px-4 py-3 font-mono font-semibold">{s.serie}</td>
                                            <td className="px-4 py-3 font-mono">
                                                {String(s.correlativo).padStart(8, '0')}
                                            </td>
                                            <td className="px-4 py-3">{s.sucursal_nombre ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                {s.is_active ? (
                                                    <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300">
                                                        Activa
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">Inactiva</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/admin/empresas/${tenant.id}/series/${s.id}/editar`}>
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => toggle(s.id)}
                                                    className={
                                                        s.is_active
                                                            ? 'text-amber-600 hover:text-amber-700'
                                                            : 'text-emerald-600 hover:text-emerald-700'
                                                    }
                                                >
                                                    {s.is_active ? 'Desactivar' : 'Activar'}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-red-600"
                                                    onClick={() => eliminar(s.id)}
                                                >
                                                    Eliminar
                                                </Button>
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
