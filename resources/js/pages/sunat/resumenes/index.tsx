import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Download, FileText, Plus, RefreshCw } from 'lucide-react';
import { StatusBadge } from '@/components/sunat/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import SunatLayout from '@/layouts/sunat-layout';
import type { SunatStatus } from '@/types';

type Resumen = {
    id: number;
    identifier: string;
    tipo: 'envio' | 'anulacion';
    fecha_referencia: string;
    fecha_envio: string;
    total_documentos: number;
    estado: SunatStatus;
    codigo: string | null;
    descripcion: string | null;
    tiene_xml: boolean;
    tiene_cdr: boolean;
    ticket: boolean;
};

type Props = { resumenes: Resumen[] };

function fmtDate(iso: string) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

export default function ResumenesIndex({ resumenes }: Props) {
    function refrescar(r: Resumen) {
        router.post(`/sunat/resumenes/${r.id}/refrescar`, {}, { preserveScroll: true });
    }

    const columns: ColumnDef<Resumen>[] = [
        {
            accessorKey: 'identifier',
            header: 'Resumen',
            meta: { label: 'Resumen', primary: true },
            cell: ({ row }) => <span className="font-mono text-xs font-medium">{row.original.identifier}</span>,
        },
        {
            accessorKey: 'tipo',
            header: 'Tipo',
            meta: { label: 'Tipo' },
            cell: ({ row }) => row.original.tipo === 'anulacion'
                ? <Badge variant="outline">Anulación</Badge>
                : <Badge variant="secondary">Envío</Badge>,
        },
        {
            accessorKey: 'fecha_referencia',
            header: 'Fecha ref.',
            meta: { label: 'Fecha ref.' },
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{fmtDate(row.original.fecha_referencia)}</span>,
        },
        {
            accessorKey: 'fecha_envio',
            header: 'Enviado',
            meta: { label: 'Enviado' },
            cell: ({ row }) => <span className="text-xs text-muted-foreground">{fmtDate(row.original.fecha_envio)}</span>,
        },
        {
            accessorKey: 'total_documentos',
            header: 'Docs',
            meta: { label: 'Docs', alignRight: true },
            cell: ({ row }) => <span className="tabular-nums">{row.original.total_documentos}</span>,
        },
        {
            accessorKey: 'estado',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <StatusBadge status={row.original.estado} />
                    {row.original.descripcion && (
                        <span className="max-w-[220px] truncate text-[11px] text-muted-foreground" title={row.original.descripcion}>
                            {row.original.codigo ? `${row.original.codigo} · ` : ''}{row.original.descripcion}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Acciones',
            enableSorting: false,
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => {
                const r = row.original;
                const noFinal = r.estado !== 'aceptado' && r.estado !== 'rechazado';
                const actions = [
                    ...(r.tiene_cdr ? [{ label: 'Descargar CDR', icon: Download, onSelect: () => { window.location.href = `/sunat/resumenes/${r.id}/cdr`; } }] : []),
                    ...(r.tiene_xml ? [{ label: 'Descargar XML', icon: FileText, onSelect: () => { window.location.href = `/sunat/resumenes/${r.id}/xml`; } }] : []),
                    ...(noFinal && r.ticket ? [{ label: 'Refrescar estado', icon: RefreshCw, separatorBefore: r.tiene_cdr || r.tiene_xml, onSelect: () => refrescar(r) }] : []),
                ];
                if (actions.length === 0) return <span className="text-muted-foreground">—</span>;
                return <DataTableRowActions actions={actions} />;
            },
        },
    ];

    return (
        <SunatLayout>
            <Head title="Resúmenes diarios" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-6">
                    <h1 className="text-xl font-semibold tracking-tight">Resúmenes diarios</h1>
                    <p className="text-sm text-muted-foreground">
                        {resumenes.length} resumen{resumenes.length !== 1 ? 'es' : ''} · envío y anulación de boletas (RC)
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={resumenes}
                    searchPlaceholder="Buscar por identificador…"
                    emptyMessage="Aún no has generado resúmenes diarios."
                    toolbar={
                        <Button onClick={() => router.visit('/sunat/resumenes/nueva')} className="gap-2 rounded-xl">
                            <Plus className="size-4" /> Generar resumen del día
                        </Button>
                    }
                />
            </div>
        </SunatLayout>
    );
}
