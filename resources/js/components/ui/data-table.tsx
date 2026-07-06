import * as React from 'react';
import {
    type ColumnDef,
    type SortingState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ArrowUpDown, ChevronDown, ChevronLeft, ChevronRight, ChevronUp, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * Metadata opcional por columna para la vista responsive (cards en móvil).
 * - label:      texto que se muestra a la izquierda en la card móvil.
 * - primary:    la columna se muestra como título de la card (sin label).
 * - hideLabel:  no mostrar el label en móvil (ej. la celda de acciones).
 * - alignRight: alinea la celda a la derecha (útil para acciones/montos).
 */
export type ColumnMeta = {
    label?: string;
    primary?: boolean;
    hideLabel?: boolean;
    alignRight?: boolean;
};

type DataTableProps<TData, TValue> = {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    searchPlaceholder?: string;
    /** Muestra el buscador global. Default: true */
    searchable?: boolean;
    /** Filas por página. Default: 10 */
    pageSize?: number;
    emptyMessage?: string;
    /** Contenido extra a la derecha de la barra de búsqueda (ej. botón "Nuevo") */
    toolbar?: React.ReactNode;
};

export function DataTable<TData, TValue>({
    columns,
    data,
    searchPlaceholder = 'Buscar...',
    searchable = true,
    pageSize = 10,
    emptyMessage = 'No hay resultados.',
    toolbar,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [globalFilter, setGlobalFilter] = React.useState('');

    const table = useReactTable({
        data,
        columns,
        state: { sorting, globalFilter },
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: { pagination: { pageSize } },
    });

    const rows = table.getRowModel().rows;

    return (
        <div className="flex flex-col gap-4">
            {/* Toolbar: búsqueda + acciones */}
            {(searchable || toolbar) && (
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {searchable ? (
                        <div className="relative w-full sm:max-w-xs">
                            <Search className="text-muted-foreground absolute left-3 top-1/2 size-4 -translate-y-1/2" />
                            <Input
                                value={globalFilter}
                                onChange={(e) => setGlobalFilter(e.target.value)}
                                placeholder={searchPlaceholder}
                                className="pl-9"
                            />
                        </div>
                    ) : (
                        <div />
                    )}
                    {toolbar}
                </div>
            )}

            {/* ── Vista escritorio (tabla) ─────────────────────────── */}
            <div className="border-border bg-card hidden rounded-xl border shadow-sm md:block">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((hg) => (
                            <TableRow key={hg.id} className="hover:bg-transparent">
                                {hg.headers.map((header) => {
                                    const canSort = header.column.getCanSort();
                                    const sorted = header.column.getIsSorted();
                                    const m = (header.column.columnDef.meta as ColumnMeta) ?? {};
                                    return (
                                        <TableHead
                                            key={header.id}
                                            className={cn(m.alignRight && 'text-right')}
                                        >
                                            {header.isPlaceholder ? null : canSort ? (
                                                <button
                                                    type="button"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    className={cn(
                                                        'hover:text-foreground inline-flex items-center gap-1.5 transition-colors',
                                                        m.alignRight && 'flex-row-reverse',
                                                    )}
                                                >
                                                    {flexRender(header.column.columnDef.header, header.getContext())}
                                                    {sorted === 'asc' ? (
                                                        <ChevronUp className="size-3.5" />
                                                    ) : sorted === 'desc' ? (
                                                        <ChevronDown className="size-3.5" />
                                                    ) : (
                                                        <ArrowUpDown className="size-3 opacity-40" />
                                                    )}
                                                </button>
                                            ) : (
                                                flexRender(header.column.columnDef.header, header.getContext())
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {rows.length ? (
                            rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => {
                                        const m = (cell.column.columnDef.meta as ColumnMeta) ?? {};
                                        return (
                                            <TableCell
                                                key={cell.id}
                                                className={cn(m.alignRight && 'text-right')}
                                            >
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        );
                                    })}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={columns.length} className="text-muted-foreground h-28 text-center">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* ── Vista móvil (cards) ──────────────────────────────── */}
            <div className="flex flex-col gap-3 md:hidden">
                {rows.length ? (
                    rows.map((row) => {
                        const cells = row.getVisibleCells();
                        const primary = cells.find(
                            (c) => ((c.column.columnDef.meta as ColumnMeta) ?? {}).primary,
                        );
                        const actions = cells.find(
                            (c) => ((c.column.columnDef.meta as ColumnMeta) ?? {}).hideLabel,
                        );
                        const rest = cells.filter((c) => c !== primary && c !== actions);

                        return (
                            <div
                                key={row.id}
                                className="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 shadow-sm"
                            >
                                {(primary || actions) && (
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 font-medium">
                                            {primary &&
                                                flexRender(primary.column.columnDef.cell, primary.getContext())}
                                        </div>
                                        {actions && (
                                            <div className="shrink-0">
                                                {flexRender(actions.column.columnDef.cell, actions.getContext())}
                                            </div>
                                        )}
                                    </div>
                                )}
                                <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                    {rest.map((cell) => {
                                        const m = (cell.column.columnDef.meta as ColumnMeta) ?? {};
                                        return (
                                            <div key={cell.id} className="flex flex-col gap-0.5">
                                                <dt className="text-muted-foreground text-[11px] uppercase tracking-wide">
                                                    {m.label ?? ''}
                                                </dt>
                                                <dd>
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </dd>
                                            </div>
                                        );
                                    })}
                                </dl>
                            </div>
                        );
                    })
                ) : (
                    <div className="border-border bg-card text-muted-foreground rounded-xl border p-8 text-center text-sm">
                        {emptyMessage}
                    </div>
                )}
            </div>

            {/* ── Paginación ───────────────────────────────────────── */}
            {table.getPageCount() > 1 && (
                <div className="flex items-center justify-between gap-2">
                    <p className="text-muted-foreground text-xs">
                        {table.getFilteredRowModel().rows.length} resultado(s) · Página{' '}
                        {table.getState().pagination.pageIndex + 1} de {table.getPageCount()}
                    </p>
                    <div className="flex gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                        >
                            <ChevronLeft className="size-4" />
                            Anterior
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                        >
                            Siguiente
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
