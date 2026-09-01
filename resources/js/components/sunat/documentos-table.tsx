import { router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { Ban, Download, FileMinus, FilePlus, FileText, Loader2, RefreshCw, Search, X } from 'lucide-react';
import { StatusBadge } from '@/components/sunat/status-badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { DataTable } from '@/components/ui/data-table';
import { DataTableRowActions } from '@/components/ui/data-table-row-actions';
import { Label } from '@/components/ui/label';
import type { DocumentoSunat } from '@/types';

const TIPO_LABEL: Record<string, string> = {
    '01': 'Factura',
    '03': 'Boleta',
    '07': 'N. Crédito',
    '08': 'N. Débito',
    '09': 'Guía rem.',
    '31': 'Guía transp.',
    '20': 'Retención',
    '40': 'Percepción',
};

function formatPen(amount: number, moneda: string) {
    return (moneda === 'USD' ? '$ ' : 'S/ ') +
        new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2 }).format(amount);
}

type Props = {
    documentos: DocumentoSunat[];
    searchPlaceholder?: string;
    emptyMessage?: string;
    /** Oculta la columna de Tipo (útil en listados de un solo tipo). Default: false */
    ocultarTipo?: boolean;
};

/**
 * Tabla reutilizable de comprobantes con todas sus acciones (descargas, consulta,
 * reenvío, notas y anulación). La usan el Historial y los listados por tipo de Emitir.
 */
export function DocumentosTable({ documentos, searchPlaceholder = 'Buscar en resultados...', emptyMessage = 'No se encontraron comprobantes.', ocultarTipo = false }: Props) {
    const confirm = useConfirm();
    const [anularDoc, setAnularDoc] = useState<DocumentoSunat | null>(null);
    const [motivo, setMotivo] = useState('');
    const [motivoError, setMotivoError] = useState('');
    const [anulando, setAnulando] = useState(false);

    async function reenviar(doc: DocumentoSunat) {
        if (await confirm({
            title: `¿Reenviar ${doc.numero} a SUNAT?`,
            description: 'Se volverá a enviar el comprobante a SUNAT para obtener su respuesta (CDR).',
            confirmText: 'Reenviar',
        })) {
            router.post(`/sunat/historial/${doc.tipo_doc}/${doc.id}/reenviar`, {}, { preserveScroll: true });
        }
    }

    function abrirAnular(doc: DocumentoSunat) {
        setAnularDoc(doc);
        setMotivo('');
        setMotivoError('');
    }

    function ejecutarAnulacion() {
        if (!anularDoc) return;
        if (motivo.trim().length < 3) {
            setMotivoError('Ingresa el motivo de la anulación (mínimo 3 caracteres).');
            return;
        }
        setAnulando(true);
        router.post(`/sunat/historial/${anularDoc.tipo_doc}/${anularDoc.id}/anular`, { motivo: motivo.trim() }, {
            preserveScroll: true,
            onSuccess: () => setAnularDoc(null),
            onFinish: () => setAnulando(false),
        });
    }

    const mecanismo = anularDoc?.tipo_doc === '03'
        ? { nombre: 'Resumen Diario de anulación (RC)', desc: 'Las boletas se anulan enviando un Resumen Diario a SUNAT. La anulación se confirma cuando SUNAT procesa el ticket.' }
        : { nombre: 'Comunicación de Baja (RA)', desc: 'Las facturas y notas se dan de baja ante SUNAT. Solo procede dentro de los 7 días de emitido y si el comprobante no tiene una nota de crédito asociada.' };
    const puedeNotaCredito = anularDoc?.tipo_doc === '01' || anularDoc?.tipo_doc === '03';

    const columns: ColumnDef<DocumentoSunat>[] = [
        ...(ocultarTipo ? [] : [{
            accessorKey: 'tipo_doc',
            header: 'Tipo',
            meta: { label: 'Tipo' },
            cell: ({ row }) => (
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                    {TIPO_LABEL[row.original.tipo_doc] ?? row.original.tipo_doc}
                </span>
            ),
        } as ColumnDef<DocumentoSunat>]),
        {
            accessorKey: 'numero',
            header: 'Número',
            meta: { label: 'Número', primary: true },
            cell: ({ row }) => <span className="font-mono text-xs">{row.original.numero}</span>,
        },
        {
            accessorKey: 'cliente',
            header: 'Cliente',
            meta: { label: 'Cliente' },
            cell: ({ row }) => <span className="block max-w-[200px] truncate">{row.original.cliente}</span>,
        },
        {
            accessorKey: 'fecha',
            header: 'Fecha',
            meta: { label: 'Fecha' },
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-sm text-muted-foreground">
                    {new Date(row.original.fecha).toLocaleDateString('es-PE')}
                </span>
            ),
        },
        {
            accessorKey: 'total',
            header: 'Total',
            meta: { label: 'Total', alignRight: true },
            cell: ({ row }) => (
                <span className="whitespace-nowrap font-medium tabular-nums">
                    {row.original.tipo_doc === '09' || row.original.tipo_doc === '31'
                        ? <span className="text-muted-foreground">—</span>
                        : formatPen(row.original.total, row.original.moneda)}
                </span>
            ),
        },
        {
            accessorKey: 'estado',
            header: 'Estado',
            meta: { label: 'Estado' },
            cell: ({ row }) => {
                const doc = row.original;
                const esTimeout = doc.estado === 'pendiente' && doc.sunat_code === 'SUNAT_TIMEOUT';
                return (
                    <div className="flex flex-col gap-0.5">
                        <StatusBadge status={doc.estado} />
                        {esTimeout && (
                            <span className="text-[10px] text-amber-600 dark:text-amber-400 leading-tight">
                                Pendiente de reintento manual
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            header: 'Acciones',
            enableSorting: false,
            meta: { hideLabel: true, alignRight: true },
            cell: ({ row }) => {
                const doc = row.original;
                const aceptado = doc.estado === 'aceptado';
                const esComprobante = ['01', '03', '07', '08'].includes(doc.tipo_doc);
                const esVenta = doc.tipo_doc === '01' || doc.tipo_doc === '03';
                const puedeReenviar = esComprobante && doc.estado !== 'aceptado' && doc.estado !== 'enviado' && doc.estado !== 'anulado' && doc.estado !== 'anulacion_en_proceso';
                const tieneDescarga = doc.tiene_pdf || doc.tiene_xml || doc.tiene_cdr;

                const actions = [
                    ...(doc.tiene_pdf ? [{ label: 'Descargar PDF', icon: FileText, onSelect: () => { window.location.href = `/sunat/historial/${doc.tipo_doc}/${doc.id}/pdf`; } }] : []),
                    ...(doc.tiene_xml ? [{ label: 'Descargar XML', icon: Download, onSelect: () => { window.location.href = `/sunat/historial/${doc.tipo_doc}/${doc.id}/xml`; } }] : []),
                    ...(doc.tiene_cdr ? [{ label: 'Descargar CDR', icon: Download, onSelect: () => { window.location.href = `/sunat/historial/${doc.tipo_doc}/${doc.id}/cdr`; } }] : []),
                    ...(doc.estado === 'anulado' && esComprobante ? [
                        { label: 'Constancia de anulación (CDR)', icon: Download, onSelect: () => { window.location.href = `/sunat/historial/${doc.tipo_doc}/${doc.id}/anulacion/cdr`; } },
                        { label: 'XML de la anulación', icon: FileText, onSelect: () => { window.location.href = `/sunat/historial/${doc.tipo_doc}/${doc.id}/anulacion/xml`; } },
                    ] : []),
                    ...(esComprobante ? [{ label: 'Consultar en SUNAT', icon: Search, separatorBefore: tieneDescarga || doc.estado === 'anulado', onSelect: () => router.visit(`/sunat/consulta-cpe?tipo=${doc.tipo_doc}&serie=${encodeURIComponent(doc.serie)}&correlativo=${doc.correlativo}&fecha=${encodeURIComponent(doc.fecha)}&monto=${doc.total}`) }] : []),
                    ...(puedeReenviar ? [{ label: doc.sunat_code === 'SUNAT_TIMEOUT' ? 'Reintentar envío' : 'Reenviar a SUNAT', icon: RefreshCw, onSelect: () => reenviar(doc) }] : []),
                    ...(aceptado && esVenta ? [{ label: 'Emitir nota de crédito', icon: FileMinus, separatorBefore: true, onSelect: () => router.visit(`/sunat/nota-credito/nueva?doc_id=${doc.id}&tipo_doc=${doc.tipo_doc}`) }] : []),
                    ...(aceptado && esVenta ? [{ label: 'Emitir nota de débito', icon: FilePlus, onSelect: () => router.visit(`/sunat/nota-debito/nueva?doc_id=${doc.id}&tipo=${doc.tipo_doc}`) }] : []),
                    ...(aceptado && esComprobante ? [{ label: 'Anular', icon: Ban, danger: true, separatorBefore: true, onSelect: () => abrirAnular(doc) }] : []),
                ];
                if (actions.length === 0) return <span className="text-muted-foreground">—</span>;
                return <DataTableRowActions actions={actions} />;
            },
        },
    ];

    return (
        <>
            <DataTable columns={columns} data={documentos} searchPlaceholder={searchPlaceholder} emptyMessage={emptyMessage} />

            {/* Modal de anulación */}
            {anularDoc && (
                <div className="fixed inset-0 z-[100] overflow-y-auto bg-black/50" onClick={() => !anulando && setAnularDoc(null)}>
                  <div className="flex min-h-full items-center justify-center p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-border bg-card p-5 shadow-soft" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-foreground">Anular comprobante</h3>
                            <button type="button" onClick={() => setAnularDoc(null)} className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary">
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="flex flex-col gap-4">
                            <div className="rounded-xl border border-border bg-muted/40 p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-mono text-sm font-medium text-foreground">{anularDoc.numero}</span>
                                    <StatusBadge status={anularDoc.estado} />
                                </div>
                                <p className="mt-1 truncate text-xs text-muted-foreground">{anularDoc.cliente}</p>
                            </div>

                            <div className="flex flex-col gap-1">
                                <p className="text-xs font-medium text-muted-foreground">Mecanismo de anulación</p>
                                <p className="text-sm text-foreground">{mecanismo.nombre}</p>
                                <p className="text-xs leading-relaxed text-muted-foreground">{mecanismo.desc}</p>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Motivo de la anulación <span className="text-destructive">*</span></Label>
                                <textarea
                                    value={motivo}
                                    onChange={(e) => { setMotivo(e.target.value); setMotivoError(''); }}
                                    rows={3}
                                    maxLength={255}
                                    placeholder="Ej: Operación no realizada / error en los datos del comprobante"
                                    className="w-full resize-none rounded-xl border border-input bg-card px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 dark:border-border dark:bg-background"
                                />
                                {motivoError && <p className="text-xs text-destructive">{motivoError}</p>}
                            </div>

                            {puedeNotaCredito && (
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    ¿Es una devolución o corrección comercial? Considera{' '}
                                    <button type="button" className="font-medium text-primary hover:underline" onClick={() => router.visit(`/sunat/nota-credito/nueva?doc_id=${anularDoc.id}&tipo_doc=${anularDoc.tipo_doc}`)}>
                                        emitir una nota de crédito
                                    </button>{' '}en lugar de anular.
                                </p>
                            )}
                        </div>

                        <div className="mt-5 flex flex-col gap-3 border-t border-border/60 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-muted-foreground">El estado cambia solo cuando SUNAT procesa la operación.</p>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="ghost" onClick={() => setAnularDoc(null)} disabled={anulando}>Cancelar</Button>
                                <Button type="button" variant="destructive" onClick={ejecutarAnulacion} disabled={anulando}>
                                    {anulando && <Loader2 className="mr-2 size-4 animate-spin" />}
                                    Confirmar anulación
                                </Button>
                            </div>
                        </div>
                    </div>
                  </div>
                </div>
            )}
        </>
    );
}
