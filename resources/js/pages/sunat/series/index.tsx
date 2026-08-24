import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Loader2, Pencil, Plus, Power, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';

type Serie = { id: number; tipo_documento: string; tipo_nombre: string; serie: string; proximo: number; sucursal_id: number | null; sucursal_nombre: string | null; is_active: boolean };
type Sucursal = { id: number; nombre: string; cod_local: string };
type Props = { series: Serie[]; sucursales: Sucursal[]; tipos: Record<string, string>; prefijos: Record<string, string[]> };

/** Marca de campo obligatorio: (*) en rojo, igual que en los forms del admin. */
const Req = () => <span className="font-bold text-[#EF233C]">(*)</span>;

export default function SeriesIndex({ series, sucursales, tipos, prefijos }: Props) {
    const confirm = useConfirm();
    const tipoKeys = Object.keys(tipos);

    const [modalOpen, setModalOpen] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);
    const [tipo, setTipo] = useState(tipoKeys[0] ?? '01');
    const [serie, setSerie] = useState('');
    const [proximo, setProximo] = useState('1');
    const [sucursalId, setSucursalId] = useState('');
    const [activa, setActiva] = useState(true);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const editando = editId !== null;
    const hint = (prefijos[tipo] ?? []).join(' o ');

    const abrirModal = () => {
        setEditId(null);
        setTipo(tipoKeys[0] ?? '01');
        setSerie('');
        setProximo('1');
        setSucursalId('');
        setActiva(true);
        setErrors({});
        setModalOpen(true);
    };

    const abrirEdicion = (s: Serie) => {
        setEditId(s.id);
        setTipo(s.tipo_documento);
        setSerie(s.serie);
        setProximo(String(s.proximo));
        setSucursalId(s.sucursal_id ? String(s.sucursal_id) : '');
        setActiva(s.is_active);
        setErrors({});
        setModalOpen(true);
    };

    const guardar = () => {
        setSaving(true);
        const payload = {
            tipo_documento: tipo,
            serie,
            correlativo: Number(proximo),
            sucursal_id: sucursalId ? Number(sucursalId) : null,
            is_active: activa,
        };
        const opts = {
            preserveScroll: true,
            onError: (e: Record<string, string>) => setErrors(e),
            onSuccess: () => setModalOpen(false),
            onFinish: () => setSaving(false),
        };
        if (editando) {
            router.put(`/sunat/series/${editId}`, payload, opts);
        } else {
            router.post('/sunat/series', payload, opts);
        }
    };

    const toggle = (s: Serie) => router.post(`/sunat/series/${s.id}/toggle`, {}, { preserveScroll: true });
    const eliminar = async (s: Serie) => {
        if (await confirm({
            title: `¿Eliminar la serie ${s.serie}?`,
            description: 'Esto no borra los documentos ya emitidos con esta serie.',
            variant: 'danger',
            confirmText: 'Eliminar',
        })) {
            router.delete(`/sunat/series/${s.id}`, { preserveScroll: true });
        }
    };

    const columns: ColumnDef<Serie>[] = [
        {
            accessorKey: 'serie',
            header: 'Serie',
            meta: { label: 'Serie', primary: true },
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-mono font-semibold">{row.original.serie}</span>
                    <span className="text-xs uppercase text-muted-foreground">
                        {row.original.tipo_nombre} ({row.original.tipo_documento})
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'proximo',
            header: 'Correlativo',
            meta: { label: 'Correlativo' },
            cell: ({ row }) => <span className="font-mono">{String(row.original.proximo).padStart(8, '0')}</span>,
        },
        {
            accessorKey: 'sucursal_nombre',
            header: 'Sucursal',
            meta: { label: 'Sucursal' },
            cell: ({ row }) => row.original.sucursal_nombre ?? <span className="text-muted-foreground">—</span>,
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
            <Head title="Series" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Series y correlativos</h1>
                        <p className="text-sm text-muted-foreground">Define las series de tus comprobantes.</p>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={series}
                    searchPlaceholder="Buscar serie..."
                    emptyMessage="Aún no hay series. Crea al menos F001 (facturas) y B001 (boletas)."
                    toolbar={
                        <Button onClick={abrirModal}>
                            <Plus className="size-4" />
                            Nueva serie
                        </Button>
                    }
                />
            </div>

            {/* Modal: nueva serie */}
            {modalOpen && (
                <div className="fixed inset-0 z-[100] overflow-y-auto bg-black/50" onClick={() => !saving && setModalOpen(false)}>
                  <div className="flex min-h-full items-center justify-center p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-soft" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-5 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-foreground">{editando ? 'Editar serie' : 'Nueva serie'}</h3>
                            <button type="button" onClick={() => setModalOpen(false)} className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary">
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <Label htmlFor="tipo_documento">Tipo de documento <Req /></Label>
                                <Combobox
                                    value={tipo}
                                    onChange={(v) => setTipo(v)}
                                    options={tipoKeys.map((k) => ({ value: k, label: `${k} - ${tipos[k]}` }))}
                                    searchable
                                    disabled={editando}
                                    className={editando ? 'opacity-70' : ''}
                                />
                                {editando && <p className="mt-1 text-xs text-muted-foreground">El tipo no se puede cambiar en una serie existente.</p>}
                            </div>

                            <div>
                                <Label htmlFor="serie">Serie <Req /></Label>
                                <Input
                                    id="serie"
                                    value={serie}
                                    onChange={(e) => setSerie(e.target.value.toUpperCase())}
                                    maxLength={4}
                                    minLength={4}
                                    pattern="[A-Z][A-Z0-9]{3}"
                                    readOnly={editando}
                                    className={`font-mono uppercase ${editando ? 'bg-muted' : ''}`}
                                    placeholder="F001"
                                />
                                {errors.serie ? (
                                    <p className="mt-1 text-xs text-red-600">{errors.serie}</p>
                                ) : (
                                    hint && <p className="mt-1 text-xs text-muted-foreground">Prefijo válido: {hint}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="correlativo">Número inicial</Label>
                                <Input
                                    id="correlativo"
                                    type="number"
                                    min={1}
                                    value={proximo}
                                    onChange={(e) => setProximo(e.target.value)}
                                    className="font-mono"
                                    placeholder="1"
                                />
                                {errors.correlativo ? (
                                    <p className="mt-1 text-xs text-red-600">{errors.correlativo}</p>
                                ) : (
                                    <p className="mt-1 text-xs text-muted-foreground">El próximo comprobante usará este número. Ej: 1 → {serie || 'F001'}-1.</p>
                                )}
                            </div>

                            <div className="md:col-span-2">
                                <Label htmlFor="sucursal_id">Sucursal</Label>
                                <Combobox
                                    value={sucursalId}
                                    onChange={(v) => setSucursalId(v)}
                                    options={[
                                        { value: '', label: 'Sin asignar' },
                                        ...sucursales.map((s) => ({ value: String(s.id), label: `${s.nombre} (${s.cod_local})` })),
                                    ]}
                                    placeholder="Sin asignar"
                                    searchable
                                />
                                {errors.sucursal_id && <p className="mt-1 text-xs text-red-600">{errors.sucursal_id}</p>}
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t pt-4">
                            <Button type="button" variant="ghost" onClick={() => setModalOpen(false)} disabled={saving}>Cancelar</Button>
                            <Button type="button" onClick={guardar} disabled={saving || serie.length !== 4}>
                                {saving && <Loader2 className="mr-2 size-4 animate-spin" />}
                                {editando ? 'Guardar cambios' : 'Crear serie'}
                            </Button>
                        </div>
                    </div>
                  </div>
                </div>
            )}
        </SunatLayout>
    );
}
