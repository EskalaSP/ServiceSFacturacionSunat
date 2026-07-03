import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type Sucursal = {
    id: number | null;
    nombre: string;
    cod_local: string;
    direccion: string;
    ubigeo: string;
    telefono: string;
    email: string;
    is_principal: boolean;
    is_active: boolean;
};

type Props = {
    tenant: { id: number; ruc: string; razon_social: string };
    sucursal: Sucursal;
    modo: 'crear' | 'editar';
};

const breadcrumbs = (razon: string, id: number, modo: 'crear' | 'editar'): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Empresas', href: '/admin/empresas' },
    { title: razon, href: `/admin/empresas/${id}` },
    { title: 'Sucursales', href: `/admin/empresas/${id}/sucursales` },
    { title: modo === 'crear' ? 'Nueva' : 'Editar', href: '#' },
];

export default function SucursalesForm({ tenant, sucursal, modo }: Props) {
    const editando = modo === 'editar';
    const { data, setData, post, put, processing, errors } = useForm({
        nombre: sucursal.nombre,
        cod_local: sucursal.cod_local,
        direccion: sucursal.direccion,
        ubigeo: sucursal.ubigeo,
        telefono: sucursal.telefono,
        email: sucursal.email,
        is_principal: sucursal.is_principal,
        is_active: sucursal.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando && sucursal.id) put(`/admin/empresas/${tenant.id}/sucursales/${sucursal.id}`);
        else post(`/admin/empresas/${tenant.id}/sucursales`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(tenant.razon_social, tenant.id, modo)}>
            <Head title={editando ? 'Editar sucursal' : 'Nueva sucursal'} />

            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {editando ? 'Editar sucursal' : 'Nueva sucursal'}
                        </h1>
                        <p className="text-sm text-muted-foreground">{tenant.razon_social}</p>
                    </div>
                    <Button variant="ghost" asChild>
                        <Link href={`/admin/empresas/${tenant.id}/sucursales`}>
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                <Card className="max-w-3xl p-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label htmlFor="nombre">Nombre *</Label>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                required
                                maxLength={100}
                                placeholder="Sucursal Principal"
                            />
                            {errors.nombre && <p className="mt-1 text-xs text-red-600">{errors.nombre}</p>}
                        </div>
                        <div>
                            <Label htmlFor="cod_local">Código local (SUNAT) *</Label>
                            <Input
                                id="cod_local"
                                value={data.cod_local}
                                onChange={(e) => setData('cod_local', e.target.value)}
                                required
                                maxLength={4}
                                minLength={4}
                                pattern="\d{4}"
                                className="font-mono"
                                placeholder="0000"
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                4 dígitos. Principal usa 0000.
                            </p>
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="direccion">Dirección</Label>
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
                                className="font-mono"
                                placeholder="150101"
                            />
                        </div>
                        <div>
                            <Label htmlFor="telefono">Teléfono</Label>
                            <Input
                                id="telefono"
                                value={data.telefono}
                                onChange={(e) => setData('telefono', e.target.value)}
                                maxLength={20}
                                placeholder="+51 999 999 999"
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                maxLength={100}
                                placeholder="sucursal@empresa.com"
                            />
                        </div>
                        <label className="inline-flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.is_principal}
                                onCheckedChange={(v) => setData('is_principal', v === true)}
                            />
                            Es sucursal principal
                        </label>
                        <label className="inline-flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.is_active}
                                onCheckedChange={(v) => setData('is_active', v === true)}
                            />
                            Sucursal activa
                        </label>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t pt-4">
                        <Button variant="ghost" asChild>
                            <Link href={`/admin/empresas/${tenant.id}/sucursales`}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Guardar cambios' : 'Crear sucursal'}
                        </Button>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
