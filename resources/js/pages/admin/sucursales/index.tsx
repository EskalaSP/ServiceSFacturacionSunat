import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';

type Sucursal = {
    id: number;
    nombre: string;
    cod_local: string;
    direccion: string | null;
    ubigeo: string | null;
    telefono: string | null;
    email: string | null;
    is_principal: boolean;
    is_active: boolean;
};

type Props = {
    tenant: { id: number; ruc: string; razon_social: string };
    sucursales: Sucursal[];
};

const breadcrumbs = (razon: string, id: number): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: razon, href: `/admin/empresas/${id}` },
    { title: 'Sucursales', href: '#' },
];

export default function SucursalesIndex({ tenant, sucursales }: Props) {
    const eliminar = (id: number) => {
        if (confirm('¿Eliminar sucursal?')) {
            router.delete(`/admin/empresas/${tenant.id}/sucursales/${id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social, tenant.id)}>
            <Head title={`Sucursales — ${tenant.razon_social}`} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Sucursales</h1>
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
                            <Link href={`/admin/empresas/${tenant.id}/sucursales/nueva`}>
                                <Plus className="size-4" />
                                Nueva sucursal
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card className="overflow-hidden p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Cód. Local</th>
                                    <th className="px-4 py-3 text-left font-medium">Nombre</th>
                                    <th className="px-4 py-3 text-left font-medium">Dirección</th>
                                    <th className="px-4 py-3 text-left font-medium">Ubigeo</th>
                                    <th className="px-4 py-3 text-left font-medium">Principal</th>
                                    <th className="px-4 py-3 text-left font-medium">Estado</th>
                                    <th className="px-4 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {sucursales.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-muted-foreground">
                                            Aún no hay sucursales.
                                        </td>
                                    </tr>
                                ) : (
                                    sucursales.map((s) => (
                                        <tr key={s.id} className="hover:bg-muted/30">
                                            <td className="px-4 py-3 font-mono text-xs">{s.cod_local}</td>
                                            <td className="px-4 py-3 font-medium">{s.nombre}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{s.direccion ?? '—'}</td>
                                            <td className="px-4 py-3 font-mono text-xs">{s.ubigeo ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                {s.is_principal && <Badge>Principal</Badge>}
                                            </td>
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
                                                    <Link href={`/admin/empresas/${tenant.id}/sucursales/${s.id}/editar`}>
                                                        Editar
                                                    </Link>
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
