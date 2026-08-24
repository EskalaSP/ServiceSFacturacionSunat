import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordInput } from '@/components/ui/password-input';
import { PasswordRequirements } from '@/components/ui/password-requirements';
import type { BreadcrumbItem } from '@/types';

type Usuario = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    empresa_id?: number | null;
};

type EmpresaOpcion = { id: number; label: string };

type Props = {
    usuario: Usuario | null;
    roles: Record<string, string>;
    empresas?: EmpresaOpcion[];
    modo: 'crear' | 'editar';
};

/** Qué puede hacer cada rol (ayuda visual). */
const roleHelp: Record<string, string> = {
    super_admin: 'Acceso total, incluida la gestión de usuarios y la eliminación de empresas.',
    admin: 'Gestiona empresas, planes, series, sucursales y usuarios. No puede eliminar empresas.',
    soporte: 'Ve empresas y comprobantes, y puede reenviar comprobantes a SUNAT.',
    lectura: 'Solo puede ver la información del panel. No modifica nada.',
    cliente: 'Dueño de una empresa. NO entra al panel admin; inicia sesión y emite sus comprobantes desde el panel, y puede crear cajeros para su empresa.',
};

const breadcrumbs = (modo: 'crear' | 'editar', name?: string): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Usuarios', href: '/admin/usuarios' },
    { title: modo === 'crear' ? 'Nuevo usuario' : `Editar: ${name ?? ''}`, href: '#' },
];

export default function UsuariosForm({ usuario, roles, empresas = [], modo }: Props) {
    const editando = modo === 'editar';
    const [password, setPassword] = useState('');

    const { data, setData, post, put, processing, errors } = useForm({
        name: usuario?.name ?? '',
        email: usuario?.email ?? '',
        password: '',
        role: usuario?.role ?? 'lectura',
        empresa_id: usuario?.empresa_id ?? (null as number | null),
        is_active: usuario?.is_active ?? true,
    });

    const esCliente = data.role === 'cliente';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editando && usuario) put(`/admin/usuarios/${usuario.id}`);
        else post('/admin/usuarios');
    };

    const roleOptions = Object.entries(roles).map(([value, label]) => ({ value, label }));

    return (
        <AppLayout breadcrumbs={breadcrumbs(modo, usuario?.name)}>
            <Head title={editando ? 'Editar usuario' : 'Nuevo usuario'} />

            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                {/* Encabezado */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {editando ? `Editar: ${usuario?.name}` : 'Nuevo usuario'}
                    </h1>
                    <Button variant="ghost" asChild>
                        <Link href="/admin/usuarios">
                            <ArrowLeft className="size-4" />
                            Volver
                        </Link>
                    </Button>
                </div>

                {/* Datos de acceso */}
                <Card className="p-6">
                    <h3 className="mb-4 text-base font-semibold">Datos de acceso</h3>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label htmlFor="name">Nombre</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                maxLength={255}
                                placeholder="Ej: Juan Pérez"
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <Label htmlFor="email">Correo</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                maxLength={255}
                                placeholder="usuario@correo.com"
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                        </div>
                        <div className="md:col-span-2">
                            <Label htmlFor="password">
                                {editando ? 'Nueva contraseña (opcional)' : 'Contraseña'}
                            </Label>
                            <PasswordInput
                                id="password"
                                value={data.password}
                                onChange={(e) => {
                                    setData('password', e.target.value);
                                    setPassword(e.target.value);
                                }}
                                required={!editando}
                                placeholder={editando ? '••••• (dejar vacío = sin cambios)' : 'Mínimo 6 caracteres'}
                            />
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                            {(!editando || password.length > 0) && (
                                <div className="mt-2">
                                    <PasswordRequirements value={password} />
                                </div>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Rol y estado */}
                <Card className="p-6">
                    <h3 className="mb-4 flex items-center gap-2 text-base font-semibold">
                        <ShieldCheck className="size-4 text-primary" />
                        Rol y permisos
                    </h3>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label htmlFor="role">Rol</Label>
                            <Combobox
                                options={roleOptions}
                                value={data.role}
                                onChange={(v) => setData('role', v)}
                                placeholder="Selecciona un rol"
                            />
                            {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
                            <p className="mt-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                {roleHelp[data.role] ?? 'Selecciona un rol para ver sus permisos.'}
                            </p>
                        </div>
                        {esCliente && (
                            <div>
                                <Label htmlFor="empresa">Empresa (de la que es dueño)</Label>
                                <Combobox
                                    options={empresas.map((e) => ({ value: String(e.id), label: e.label }))}
                                    value={data.empresa_id ? String(data.empresa_id) : ''}
                                    onChange={(v) => setData('empresa_id', v ? Number(v) : null)}
                                    placeholder={empresas.length ? 'Selecciona una empresa' : 'Primero crea una empresa'}
                                    searchable
                                />
                                {errors.empresa_id && <p className="mt-1 text-xs text-red-600">{errors.empresa_id}</p>}
                                <p className="mt-2 text-xs text-muted-foreground">
                                    El cliente será el dueño de esta empresa y podrá emitir sus comprobantes.
                                </p>
                            </div>
                        )}
                        <div>
                            <Label>Estado</Label>
                            <label className="mt-2 flex cursor-pointer items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.is_active}
                                    onCheckedChange={(v) => setData('is_active', v === true)}
                                />
                                Cuenta activa (puede iniciar sesión)
                            </label>
                        </div>
                    </div>
                </Card>

                <div className="sticky bottom-0 -mx-4 flex justify-end gap-3 border-t bg-background/80 px-4 py-3 backdrop-blur">
                    <Button variant="ghost" asChild>
                        <Link href="/admin/usuarios">Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {editando ? 'Guardar cambios' : 'Crear usuario'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
