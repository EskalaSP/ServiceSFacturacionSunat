import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { Edit2, Loader2, Plus, Search, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

type Cliente = {
    id: number;
    tipo_documento: string;
    numero_documento: string;
    razon_social: string;
    nombre_comercial: string | null;
    direccion: string | null;
    email: string | null;
    telefono: string | null;
};

type Props = {
    clientes: Cliente[];
    tenant: { environment: string };
};

const TIPO_DOC_LABEL: Record<string, string> = {
    '6': 'RUC', '1': 'DNI', '4': 'CE', '7': 'Pasaporte', '0': 'Otros',
};

type FormData = {
    tipo_documento: string;
    numero_documento: string;
    razon_social: string;
    nombre_comercial: string;
    direccion: string;
    email: string;
    telefono: string;
};

const EMPTY_FORM: FormData = {
    tipo_documento: '6', numero_documento: '', razon_social: '',
    nombre_comercial: '', direccion: '', email: '', telefono: '',
};

export default function ClientesIndex({ clientes }: Props) {
    const confirm = useConfirm();
    const [modalOpen, setModalOpen] = useState(false);
    const [editId, setEditId]       = useState<number | null>(null);
    const [form, setForm]           = useState<FormData>(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);
    const [lookingUp, setLookingUp] = useState(false);
    const [lookupError, setLookupError] = useState('');
    const [formErrors, setFormErrors] = useState<Partial<FormData>>({});

    function abrirNuevo() {
        setEditId(null);
        setForm(EMPTY_FORM);
        setFormErrors({});
        setLookupError('');
        setModalOpen(true);
    }

    function abrirEditar(c: Cliente) {
        setEditId(c.id);
        setForm({
            tipo_documento:   c.tipo_documento,
            numero_documento: c.numero_documento,
            razon_social:     c.razon_social,
            nombre_comercial: c.nombre_comercial ?? '',
            direccion:        c.direccion ?? '',
            email:            c.email ?? '',
            telefono:         c.telefono ?? '',
        });
        setFormErrors({});
        setLookupError('');
        setModalOpen(true);
    }

    function setField(key: keyof FormData, value: string) {
        setForm((prev) => ({ ...prev, [key]: value }));
        if (formErrors[key]) setFormErrors((prev) => ({ ...prev, [key]: '' }));
    }

    async function lookupRuc() {
        if (!form.numero_documento) return;
        setLookingUp(true);
        setLookupError('');
        try {
            const res = await fetch(
                `/sunat/buscar-ruc?numero=${encodeURIComponent(form.numero_documento)}&tipo=${form.tipo_documento}`,
                { headers: { Accept: 'application/json' } }
            );
            const data = await res.json();
            if (res.ok) {
                setForm((prev) => ({
                    ...prev,
                    razon_social: data.razon_social || prev.razon_social,
                    direccion:    data.direccion    || prev.direccion,
                }));
            } else {
                setLookupError(data.error ?? 'No encontrado');
            }
        } catch {
            setLookupError('Error de conexión');
        } finally {
            setLookingUp(false);
        }
    }

    function validate(): boolean {
        const e: Partial<FormData> = {};
        if (!form.numero_documento) e.numero_documento = 'Requerido';
        if (!form.razon_social)     e.razon_social     = 'Requerido';
        setFormErrors(e);
        return Object.keys(e).length === 0;
    }

    function guardar() {
        if (!validate()) return;
        setSubmitting(true);

        const opts = {
            preserveScroll: true,
            onSuccess: () => { setModalOpen(false); setSubmitting(false); },
            onError:   () => setSubmitting(false),
        };

        if (editId) router.put(`/sunat/clientes/${editId}`, form, opts);
        else        router.post('/sunat/clientes', form, opts);
    }

    async function eliminar(c: Cliente) {
        if (await confirm({
            title: `¿Eliminar a ${c.razon_social}?`,
            description: 'Esta acción no se puede deshacer.',
            variant: 'danger',
            confirmText: 'Eliminar',
        })) {
            router.delete(`/sunat/clientes/${c.id}`, { preserveScroll: true });
        }
    }

    const columns: ColumnDef<Cliente>[] = [
        {
            accessorKey: 'numero_documento',
            header: 'Documento',
            meta: { label: 'Documento' },
            cell: ({ row }) => (
                <div className="whitespace-nowrap">
                    <span className="mr-1.5 inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                        {TIPO_DOC_LABEL[row.original.tipo_documento] ?? row.original.tipo_documento}
                    </span>
                    <span className="font-mono text-xs">{row.original.numero_documento}</span>
                </div>
            ),
        },
        {
            accessorKey: 'razon_social',
            header: 'Razón social',
            meta: { label: 'Razón social', primary: true },
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.razon_social}</p>
                    {row.original.nombre_comercial && (
                        <p className="text-[11px] text-muted-foreground">{row.original.nombre_comercial}</p>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'direccion',
            header: 'Dirección',
            meta: { label: 'Dirección' },
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.direccion || '—'}</span>,
        },
        {
            accessorKey: 'email',
            header: 'Email',
            meta: { label: 'Email' },
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.email || '—'}</span>,
        },
        {
            accessorKey: 'telefono',
            header: 'Teléfono',
            meta: { label: 'Teléfono' },
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.telefono || '—'}</span>,
        },
        {
            id: 'actions',
            header: 'Acciones',
            enableSorting: false,
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => (
                <DataTableRowActions
                    actions={[
                        { label: 'Editar', icon: Edit2, onSelect: () => abrirEditar(row.original) },
                        { label: 'Eliminar', icon: Trash2, danger: true, separatorBefore: true, onSelect: () => eliminar(row.original) },
                    ]}
                />
            ),
        },
    ];

    return (
        <SunatLayout>
            <Head title="Clientes" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-6">
                    <h1 className="text-xl font-semibold tracking-tight">Clientes</h1>
                    <p className="text-sm text-muted-foreground">
                        {clientes.length} cliente{clientes.length !== 1 ? 's' : ''} registrado{clientes.length !== 1 ? 's' : ''}
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={clientes}
                    searchPlaceholder="Buscar por RUC, DNI o razón social..."
                    emptyMessage="No hay clientes registrados. Se crean al emitir una factura, o agrégalos manualmente."
                    toolbar={
                        <Button onClick={abrirNuevo} className="gap-2 rounded-xl">
                            <Plus className="size-4" /> Nuevo Cliente
                        </Button>
                    }
                />
            </div>

            {/* ══ MODAL CREAR / EDITAR ══ */}
            {modalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={() => setModalOpen(false)} />
                    <div className="relative w-full max-w-lg rounded-2xl border border-border bg-card shadow-soft">

                        {/* Header modal */}
                        <div className="flex items-center justify-between border-b border-border/60 px-5 py-4">
                            <h2 className="text-base font-semibold">{editId ? 'Editar cliente' : 'Nuevo cliente'}</h2>
                            <button type="button" onClick={() => setModalOpen(false)}
                                className="rounded-lg p-1.5 text-muted-foreground hover:bg-secondary">
                                <X className="size-4" />
                            </button>
                        </div>

                        {/* Body modal */}
                        <div className="grid gap-4 p-5">

                            {/* Tipo doc + Número + Lookup */}
                            <div className="grid grid-cols-[120px_1fr_auto] gap-2 items-end">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Tipo doc.</Label>
                                    <Combobox
                                        value={form.tipo_documento}
                                        onChange={(v) => setField('tipo_documento', v)}
                                        disabled={!!editId}
                                        options={[
                                            { value: '6', label: 'RUC' },
                                            { value: '1', label: 'DNI' },
                                            { value: '4', label: 'Carné Extra.' },
                                            { value: '7', label: 'Pasaporte' },
                                            { value: '0', label: 'Otros' },
                                        ]}
                                        className="h-10 rounded-xl"
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Número <span className="text-destructive">*</span></Label>
                                    <Input value={form.numero_documento} onChange={(e) => setField('numero_documento', e.target.value)}
                                        disabled={!!editId}
                                        placeholder={form.tipo_documento === '6' ? '20123456789' : '12345678'}
                                        className={`h-10 rounded-xl disabled:opacity-60 ${formErrors.numero_documento ? 'border-destructive' : ''}`}
                                    />
                                    {formErrors.numero_documento && <p className="text-xs text-destructive">{formErrors.numero_documento}</p>}
                                </div>
                                {!editId && (
                                    <button type="button" onClick={lookupRuc} disabled={lookingUp || !form.numero_documento}
                                        className="flex h-10 items-center gap-1.5 rounded-xl border border-border bg-secondary px-3 text-xs font-medium hover:bg-muted disabled:opacity-50">
                                        {lookingUp ? <Loader2 className="size-3.5 animate-spin" /> : <Search className="size-3.5" />}
                                        Consultar
                                    </button>
                                )}
                            </div>

                            {lookupError && (
                                <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                    {lookupError}
                                </p>
                            )}

                            {/* Razón social */}
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Razón social / Nombre completo <span className="text-destructive">*</span></Label>
                                <Input value={form.razon_social} onChange={(e) => setField('razon_social', e.target.value)}
                                    className={`h-10 rounded-xl ${formErrors.razon_social ? 'border-destructive' : ''}`} />
                                {formErrors.razon_social && <p className="text-xs text-destructive">{formErrors.razon_social}</p>}
                            </div>

                            {/* Nombre comercial */}
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Nombre comercial (opcional)</Label>
                                <Input value={form.nombre_comercial} onChange={(e) => setField('nombre_comercial', e.target.value)} className="h-10 rounded-xl" />
                            </div>

                            {/* Dirección */}
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Dirección (opcional)</Label>
                                <Input value={form.direccion} onChange={(e) => setField('direccion', e.target.value)} className="h-10 rounded-xl" />
                            </div>

                            {/* Email + Teléfono */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Email (opcional)</Label>
                                    <Input type="email" value={form.email} onChange={(e) => setField('email', e.target.value)} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Teléfono (opcional)</Label>
                                    <Input value={form.telefono} onChange={(e) => setField('telefono', e.target.value)} className="h-10 rounded-xl" />
                                </div>
                            </div>
                        </div>

                        {/* Footer modal */}
                        <div className="flex justify-end gap-3 border-t border-border/60 px-5 py-4">
                            <Button variant="outline" onClick={() => setModalOpen(false)} className="rounded-xl">Cancelar</Button>
                            <Button onClick={guardar} disabled={submitting} className="gap-2 rounded-xl">
                                {submitting && <Loader2 className="size-4 animate-spin" />}
                                {editId ? 'Guardar cambios' : 'Crear cliente'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </SunatLayout>
    );
}
