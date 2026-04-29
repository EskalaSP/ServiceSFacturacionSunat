import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Edit2, Loader2, Plus, Search, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
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

type PaginatedClientes = {
    data: Cliente[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    clientes: PaginatedClientes;
    filtros: { q: string };
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

export default function ClientesIndex({ clientes, filtros }: Props) {
    const [q, setQ]                 = useState(filtros.q);
    const [modalOpen, setModalOpen] = useState(false);
    const [editId, setEditId]       = useState<number | null>(null);
    const [form, setForm]           = useState<FormData>(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);
    const [lookingUp, setLookingUp] = useState(false);
    const [lookupError, setLookupError] = useState('');
    const [confirmarId, setConfirmarId] = useState<number | null>(null);
    const [formErrors, setFormErrors] = useState<Partial<FormData>>({});

    function buscar() {
        router.get('/sunat/clientes', { q }, { preserveState: true });
    }

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

        if (editId) {
            router.put(`/sunat/clientes/${editId}`, form, {
                onSuccess: () => { setModalOpen(false); setSubmitting(false); },
                onError:   () => setSubmitting(false),
            });
        } else {
            router.post('/sunat/clientes', form, {
                onSuccess: () => { setModalOpen(false); setSubmitting(false); },
                onError:   () => setSubmitting(false),
            });
        }
    }

    function eliminar(id: number) {
        router.delete(`/sunat/clientes/${id}`, { onFinish: () => setConfirmarId(null) });
    }

    return (
        <SunatLayout>
            <Head title="Clientes" />

            <div className="mx-auto max-w-6xl">

                {/* Header */}
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Clientes</h1>
                        <p className="text-sm text-muted-foreground">{clientes.total} cliente{clientes.total !== 1 ? 's' : ''} registrado{clientes.total !== 1 ? 's' : ''}</p>
                    </div>
                    <Button onClick={abrirNuevo} className="gap-2 rounded-xl">
                        <Plus className="size-4" /> Nuevo Cliente
                    </Button>
                </div>

                {/* Búsqueda */}
                <div className="mb-5 flex gap-3">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por RUC, DNI o razón social..."
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && buscar()}
                            className="h-9 rounded-xl pl-9"
                        />
                    </div>
                    <Button variant="outline" size="sm" onClick={buscar} className="h-9 rounded-xl">Buscar</Button>
                </div>

                {/* Tabla */}
                <div className="rounded-2xl border border-border bg-card shadow-soft overflow-hidden">
                    {clientes.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <p className="text-sm font-medium text-muted-foreground">No hay clientes registrados</p>
                            <p className="mt-1 text-xs text-muted-foreground">Los clientes se crean al emitir una factura o puedes agregarlos manualmente.</p>
                            <Button onClick={abrirNuevo} className="mt-4 gap-2 rounded-xl" size="sm">
                                <Plus className="size-3.5" /> Nuevo Cliente
                            </Button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border/60 bg-muted/30">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Doc.</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Razón social</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Dirección</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Email</th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Teléfono</th>
                                        <th className="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {clientes.data.map((c) => (
                                        <tr key={c.id} className="border-b border-border/40 last:border-0 hover:bg-muted/20 transition-colors">
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground mr-1.5">
                                                    {TIPO_DOC_LABEL[c.tipo_documento] ?? c.tipo_documento}
                                                </span>
                                                <span className="font-mono text-xs">{c.numero_documento}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="font-medium">{c.razon_social}</p>
                                                {c.nombre_comercial && <p className="text-[11px] text-muted-foreground">{c.nombre_comercial}</p>}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground max-w-[180px] truncate">{c.direccion ?? '—'}</td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">{c.email ?? '—'}</td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">{c.telefono ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-center gap-1.5">
                                                    <button type="button" onClick={() => abrirEditar(c)}
                                                        className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:text-primary">
                                                        <Edit2 className="size-3.5" />
                                                    </button>
                                                    {confirmarId === c.id ? (
                                                        <div className="flex items-center gap-1">
                                                            <button type="button" onClick={() => eliminar(c.id)}
                                                                className="rounded-lg bg-destructive px-2 py-1 text-[11px] font-medium text-destructive-foreground">
                                                                Confirmar
                                                            </button>
                                                            <button type="button" onClick={() => setConfirmarId(null)}
                                                                className="rounded-lg border px-2 py-1 text-[11px] text-muted-foreground">
                                                                No
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <button type="button" onClick={() => setConfirmarId(c.id)}
                                                            className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:text-destructive">
                                                            <Trash2 className="size-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Paginación */}
                {clientes.last_page > 1 && (
                    <div className="mt-4 flex justify-center gap-1">
                        {clientes.links.map((link, i) => (
                            <button key={i} type="button" disabled={!link.url || link.active}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`rounded-lg px-3 py-1.5 text-xs transition-colors ${
                                    link.active ? 'bg-primary text-primary-foreground font-medium'
                                        : link.url ? 'border border-border hover:bg-secondary'
                                        : 'text-muted-foreground cursor-not-allowed'
                                }`}
                            />
                        ))}
                    </div>
                )}
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
                                    <select value={form.tipo_documento} onChange={(e) => setField('tipo_documento', e.target.value)}
                                        disabled={!!editId}
                                        className="h-10 rounded-xl border border-border bg-background px-3 text-sm outline-none disabled:opacity-60">
                                        <option value="6">RUC</option>
                                        <option value="1">DNI</option>
                                        <option value="4">Carné Extra.</option>
                                        <option value="7">Pasaporte</option>
                                        <option value="0">Otros</option>
                                    </select>
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Número</Label>
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
                                <Label className="text-xs font-medium text-muted-foreground">Razón social / Nombre completo</Label>
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
                                <Label className="text-xs font-medium text-muted-foreground">Dirección</Label>
                                <Input value={form.direccion} onChange={(e) => setField('direccion', e.target.value)} className="h-10 rounded-xl" />
                            </div>

                            {/* Email + Teléfono */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Email</Label>
                                    <Input type="email" value={form.email} onChange={(e) => setField('email', e.target.value)} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Teléfono</Label>
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
