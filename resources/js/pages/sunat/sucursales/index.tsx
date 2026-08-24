import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Loader2, Pencil, Plus, Power, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

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
type Props = { sucursales: Sucursal[] };

/** Marca de campo obligatorio: (*) en rojo, igual que en los forms del admin. */
const Req = () => <span className="font-bold text-[#EF233C]">(*)</span>;

type Form = {
    nombre: string; cod_local: string; direccion: string; ubigeo: string;
    telefono: string; email: string; is_principal: boolean; is_active: boolean;
};

const vacio: Form = { nombre: '', cod_local: '0000', direccion: '', ubigeo: '', telefono: '', email: '', is_principal: false, is_active: true };

export default function SucursalesIndex({ sucursales }: Props) {
    const confirm = useConfirm();

    const [modalOpen, setModalOpen] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);
    const [form, setForm] = useState<Form>(vacio);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const editando = editId !== null;
    const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm((f) => ({ ...f, [k]: v }));

    const abrirModal = () => {
        setEditId(null);
        setForm(vacio);
        setErrors({});
        setModalOpen(true);
    };

    const abrirEdicion = (s: Sucursal) => {
        setEditId(s.id);
        setForm({
            nombre: s.nombre,
            cod_local: s.cod_local,
            direccion: s.direccion ?? '',
            ubigeo: s.ubigeo ?? '',
            telefono: s.telefono ?? '',
            email: s.email ?? '',
            is_principal: s.is_principal,
            is_active: s.is_active,
        });
        setErrors({});
        setModalOpen(true);
    };

    const guardar = () => {
        setSaving(true);
        const opts = {
            preserveScroll: true,
            onError: (e: Record<string, string>) => setErrors(e),
            onSuccess: () => setModalOpen(false),
            onFinish: () => setSaving(false),
        };
        if (editando) {
            router.put(`/sunat/sucursales/${editId}`, form, opts);
        } else {
            router.post('/sunat/sucursales', form, opts);
        }
    };

    const toggle = (s: Sucursal) => router.post(`/sunat/sucursales/${s.id}/toggle`, {}, { preserveScroll: true });

    const eliminar = async (s: Sucursal) => {
        if (await confirm({
            title: `¿Eliminar la sucursal ${s.nombre}?`,
            description: 'Las series asignadas a esta sucursal quedarán sin sucursal. No se borran los documentos ya emitidos.',
            variant: 'danger',
            confirmText: 'Eliminar',
        })) {
            router.delete(`/sunat/sucursales/${s.id}`, { preserveScroll: true });
        }
    };

    const columns: ColumnDef<Sucursal>[] = [
        {
            accessorKey: 'nombre',
            header: 'Nombre',
            meta: { label: 'Nombre', primary: true },
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium">{row.original.nombre}</span>
                    {row.original.is_principal && <Badge className="text-[10px]">Principal</Badge>}
                </div>
            ),
        },
        {
            accessorKey: 'cod_local',
            header: 'Cód. Local',
            meta: { label: 'Cód. Local' },
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.cod_local}</span>,
        },
        {
            accessorKey: 'direccion',
            header: 'Dirección',
            meta: { label: 'Dirección' },
            cell: ({ row }) => <span className="block max-w-[240px] truncate text-muted-foreground">{row.original.direccion ?? '—'}</span>,
        },
        {
            accessorKey: 'ubigeo',
            header: 'Ubigeo',
            meta: { label: 'Ubigeo' },
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.ubigeo ?? '—'}</span>,
        },
        {
            accessorKey: 'is_active',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) => row.original.is_active
                ? <Badge className="border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Activa</Badge>
                : <Badge variant="secondary">Inactiva</Badge>,
        },
        {
            id: 'actions',
            header: '',
            enableSorting: false,
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => (
                <DataTableRowActions
                    actions={[
                        { label: 'Editar', icon: Pencil, onSelect: () => abrirEdicion(row.original) },
                        { label: row.original.is_active ? 'Desactivar' : 'Activar', icon: Power, onSelect: () => toggle(row.original) },
                        { label: 'Eliminar', icon: Trash2, danger: true, separatorBefore: true, onSelect: () => eliminar(row.original) },
                    ]}
                />
            ),
        },
    ];

    return (
        <SunatLayout>
            <Head title="Sucursales" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Sucursales</h1>
                        <p className="text-sm text-muted-foreground">Establecimientos anexos de tu empresa (código de local SUNAT).</p>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={sucursales}
                    searchPlaceholder="Buscar sucursal..."
                    emptyMessage="Aún no tienes sucursales. Crea al menos la principal (código 0000)."
                    toolbar={
                        <Button onClick={abrirModal}>
                            <Plus className="size-4" />
                            Nueva sucursal
                        </Button>
                    }
                />
            </div>

            {/* Modal: nueva / editar sucursal */}
            {modalOpen && (
                <div className="fixed inset-0 z-[100] overflow-y-auto bg-black/50" onClick={() => !saving && setModalOpen(false)}>
                  <div className="flex min-h-full items-center justify-center p-4">
                    <div className="w-full max-w-2xl rounded-2xl border border-border bg-card p-6 shadow-soft" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-5 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-foreground">{editando ? 'Editar sucursal' : 'Nueva sucursal'}</h3>
                            <button type="button" onClick={() => setModalOpen(false)} className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary">
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label htmlFor="nombre">Nombre <Req /></Label>
                                <Input id="nombre" value={form.nombre} onChange={(e) => set('nombre', e.target.value)} maxLength={100} placeholder="Tienda Centro" />
                                {errors.nombre && <p className="mt-1 text-xs text-red-600">{errors.nombre}</p>}
                            </div>
                            <div>
                                <Label htmlFor="cod_local">Código de local <Req /></Label>
                                <Input id="cod_local" value={form.cod_local} onChange={(e) => set('cod_local', e.target.value.replace(/\D/g, '').slice(0, 4))} maxLength={4} className="font-mono" placeholder="0000" />
                                {errors.cod_local ? <p className="mt-1 text-xs text-red-600">{errors.cod_local}</p> : <p className="mt-1 text-xs text-muted-foreground">4 dígitos. La principal suele ser 0000.</p>}
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="direccion">Dirección <Req /></Label>
                                <Input id="direccion" value={form.direccion} onChange={(e) => set('direccion', e.target.value)} maxLength={500} placeholder="Av. Ejemplo 123" />
                                {errors.direccion && <p className="mt-1 text-xs text-red-600">{errors.direccion}</p>}
                            </div>
                            <div>
                                <Label htmlFor="ubigeo">Ubigeo <Req /></Label>
                                <Input id="ubigeo" value={form.ubigeo} onChange={(e) => set('ubigeo', e.target.value.replace(/\D/g, '').slice(0, 6))} maxLength={6} className="font-mono" placeholder="150101" />
                                {errors.ubigeo && <p className="mt-1 text-xs text-red-600">{errors.ubigeo}</p>}
                            </div>
                            <div>
                                <Label htmlFor="telefono">Teléfono</Label>
                                <Input id="telefono" value={form.telefono} onChange={(e) => set('telefono', e.target.value)} maxLength={20} placeholder="(01) 555-5555" />
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="email">Correo</Label>
                                <Input id="email" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} maxLength={100} placeholder="sucursal@empresa.com" />
                                {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                            </div>
                            <label className="md:col-span-2 inline-flex items-center gap-2 text-sm">
                                <Checkbox checked={form.is_principal} onCheckedChange={(v) => set('is_principal', v === true)} />
                                Marcar como sucursal principal
                            </label>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t pt-4">
                            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={saving}>Cancelar</Button>
                            <Button type="button" onClick={guardar} disabled={saving || !form.nombre || form.cod_local.length !== 4 || !form.direccion || form.ubigeo.length !== 6}>
                                {saving && <Loader2 className="mr-2 size-4 animate-spin" />}
                                {editando ? 'Guardar cambios' : 'Crear sucursal'}
                            </Button>
                        </div>
                    </div>
                  </div>
                </div>
            )}
        </SunatLayout>
    );
}
