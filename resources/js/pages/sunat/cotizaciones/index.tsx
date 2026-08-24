import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Plus, RefreshCw, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import SunatLayout from '@/layouts/sunat-layout';

type Cotizacion = {
    id: number;
    numero: string;
    fecha_emision: string;
    fecha_vencimiento: string | null;
    cliente: string;
    total: number;
    moneda: string;
    status: 'vigente' | 'aceptada' | 'rechazada' | 'vencida';
    observacion: string | null;
};

type Props = {
    cotizaciones: Cotizacion[];
    tenant: { ruc: string; razon_social: string; environment: string };
};

const STATUS_CONFIG: Record<string, { label: string; className: string }> = {
    vigente:   { label: 'Vigente',   className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
    aceptada:  { label: 'Aceptada',  className: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' },
    rechazada: { label: 'Rechazada', className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
    vencida:   { label: 'Vencida',   className: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' },
};

function fmt(n: number) {
    return new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2 }).format(n);
}

function fmtDate(iso: string | null) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

export default function CotizacionesIndex({ cotizaciones }: Props) {
    const confirm = useConfirm();

    function cambiarEstado(id: number, nuevoEstado: string) {
        router.patch(`/sunat/cotizaciones/${id}/estado`, { status: nuevoEstado }, { preserveScroll: true });
    }

    function convertir(id: number) {
        router.post(`/sunat/cotizaciones/${id}/convertir`);
    }

    async function eliminar(cot: Cotizacion) {
        if (await confirm({
            title: `¿Eliminar la cotización ${cot.numero}?`,
            description: 'Esta acción no se puede deshacer.',
            variant: 'danger',
            confirmText: 'Eliminar',
        })) {
            router.delete(`/sunat/cotizaciones/${cot.id}`, { preserveScroll: true });
        }
    }

    const columns: ColumnDef<Cotizacion>[] = [
        {
            accessorKey: 'numero',
            header: 'Número',
            meta: { label: 'Número', primary: true },
            cell: ({ row }) => <span className="font-mono text-xs font-medium">{row.original.numero}</span>,
        },
        {
            accessorKey: 'cliente',
            header: 'Cliente',
            meta: { label: 'Cliente' },
            cell: ({ row }) => (
                <div>
                    <span className="font-medium">{row.original.cliente}</span>
                    {row.original.observacion && (
                        <p className="mt-0.5 text-[11px] text-muted-foreground line-clamp-1">{row.original.observacion}</p>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'fecha_emision',
            header: 'Fecha',
            meta: { label: 'Fecha' },
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{fmtDate(row.original.fecha_emision)}</span>,
        },
        {
            accessorKey: 'fecha_vencimiento',
            header: 'Vence',
            meta: { label: 'Vence' },
            cell: ({ row }) => row.original.fecha_vencimiento
                ? <span className="text-xs font-medium text-amber-600">{fmtDate(row.original.fecha_vencimiento)}</span>
                : <span className="text-xs text-muted-foreground">—</span>,
        },
        {
            accessorKey: 'total',
            header: 'Total',
            meta: { label: 'Total', alignRight: true },
            cell: ({ row }) => (
                <span className="font-semibold tabular-nums">
                    {row.original.moneda === 'USD' ? '$' : 'S/'} {fmt(row.original.total)}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) => {
                const st = STATUS_CONFIG[row.original.status] ?? STATUS_CONFIG.vigente;
                return (
                    <Combobox
                        value={row.original.status}
                        onChange={(v) => cambiarEstado(row.original.id, v)}
                        options={[
                            { value: 'vigente', label: 'Vigente' },
                            { value: 'aceptada', label: 'Aceptada' },
                            { value: 'rechazada', label: 'Rechazada' },
                            { value: 'vencida', label: 'Vencida' },
                        ]}
                        className={`h-auto w-auto rounded-full ${st.className}`}
                    />
                );
            },
        },
        {
            id: 'actions',
            header: 'Acciones',
            enableSorting: false,
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => {
                const cot = row.original;
                const puedeFacturar = cot.status === 'vigente' || cot.status === 'aceptada';
                return (
                    <DataTableRowActions
                        actions={[
                            ...(puedeFacturar ? [{ label: 'Convertir en factura', icon: RefreshCw, onSelect: () => convertir(cot.id) }] : []),
                            { label: 'Eliminar', icon: Trash2, danger: true, separatorBefore: puedeFacturar, onSelect: () => eliminar(cot) },
                        ]}
                    />
                );
            },
        },
    ];

    return (
        <SunatLayout>
            <Head title="Cotizaciones" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-6">
                    <h1 className="text-xl font-semibold tracking-tight">Cotizaciones</h1>
                    <p className="text-sm text-muted-foreground">
                        {cotizaciones.length} cotización{cotizaciones.length !== 1 ? 'es' : ''} registrada{cotizaciones.length !== 1 ? 's' : ''}
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={cotizaciones}
                    searchPlaceholder="Buscar por número o cliente..."
                    emptyMessage="No hay cotizaciones. Crea la primera para enviar a un cliente."
                    toolbar={
                        <Button onClick={() => router.visit('/sunat/cotizaciones/nueva')} className="gap-2 rounded-xl">
                            <Plus className="size-4" /> Nueva Cotización
                        </Button>
                    }
                />
            </div>
        </SunatLayout>
    );
}
