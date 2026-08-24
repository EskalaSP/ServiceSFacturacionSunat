import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import SunatLayout from '@/layouts/sunat-layout';

type NotaVenta = {
    id: number;
    numero: string;
    cliente: string;
    fecha: string;
    total: number;
    moneda: string;
    estado: string;
    tiene_pdf: boolean;
};

type Props = { notas: NotaVenta[] };

function fmtDate(iso: string) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function fmt(n: number, moneda: string) {
    return (moneda === 'USD' ? '$ ' : 'S/ ') + new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2 }).format(n);
}

export default function NotaVentaIndex({ notas }: Props) {
    const columns: ColumnDef<NotaVenta>[] = [
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
            cell: ({ row }) => <span className="block max-w-[220px] truncate">{row.original.cliente}</span>,
        },
        {
            accessorKey: 'fecha',
            header: 'Fecha',
            meta: { label: 'Fecha' },
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{fmtDate(row.original.fecha)}</span>,
        },
        {
            accessorKey: 'total',
            header: 'Total',
            meta: { label: 'Total', alignRight: true },
            cell: ({ row }) => <span className="font-medium tabular-nums">{fmt(row.original.total, row.original.moneda)}</span>,
        },
        {
            accessorKey: 'estado',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) => row.original.estado === 'anulada'
                ? <Badge variant="outline">Anulada</Badge>
                : <Badge variant="secondary">Emitida</Badge>,
        },
    ];

    return (
        <SunatLayout>
            <Head title="Notas de venta" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-6">
                    <h1 className="text-xl font-semibold tracking-tight">Notas de venta</h1>
                    <p className="text-sm text-muted-foreground">Documento interno (no se envía a SUNAT).</p>
                </div>

                <div className="mb-4 flex justify-end">
                    <Button onClick={() => router.visit('/sunat/nota-venta/nueva')} className="gap-2 rounded-xl">
                        <Plus className="size-4" /> Nueva nota de venta
                    </Button>
                </div>

                <DataTable
                    columns={columns}
                    data={notas}
                    searchPlaceholder="Buscar por número o cliente..."
                    emptyMessage="Aún no has registrado notas de venta."
                />
            </div>
        </SunatLayout>
    );
}
