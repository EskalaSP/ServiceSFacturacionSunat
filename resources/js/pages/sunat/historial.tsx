import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Download, FileText, Filter, Search, X } from 'lucide-react';
import { StatusBadge } from '@/components/sunat/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SunatLayout from '@/layouts/sunat-layout';
import type { DocumentoSunat } from '@/types';

type Filtros = {
    tipo: string;
    estado: string;
    desde: string;
    hasta: string;
    cliente: string;
};

type Props = {
    documentos: { data: DocumentoSunat[]; total: number } | { data: DocumentoSunat[]; total: number; current_page: number; last_page: number };
    filtros: Filtros;
    tenant: { environment: string };
};

const TIPO_LABEL: Record<string, string> = {
    '01': 'Factura',
    '03': 'Boleta',
    '07': 'N. Crédito',
    '08': 'N. Débito',
};

function formatPen(amount: number, moneda: string) {
    return (moneda === 'USD' ? '$ ' : 'S/ ') +
        new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2 }).format(amount);
}

export default function Historial({ documentos, filtros, tenant }: Props) {
    const [tipo, setTipo]       = useState(filtros.tipo ?? 'todos');
    const [estado, setEstado]   = useState(filtros.estado ?? '');
    const [desde, setDesde]     = useState(filtros.desde ?? '');
    const [hasta, setHasta]     = useState(filtros.hasta ?? '');
    const [cliente, setCliente] = useState(filtros.cliente ?? '');
    const [showFiltros, setShowFiltros] = useState(false);

    const docs = documentos.data ?? [];

    function aplicarFiltros() {
        router.get('/sunat/historial', { tipo, estado, desde, hasta, cliente }, { preserveState: true });
    }

    function limpiarFiltros() {
        setTipo('todos');
        setEstado('');
        setDesde('');
        setHasta('');
        setCliente('');
        router.get('/sunat/historial', {}, { preserveState: false });
    }

    const hayFiltros = estado || desde || hasta || cliente || tipo !== 'todos';

    return (
        <SunatLayout>
            <Head title="Historial de Comprobantes" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Historial</h1>
                        <p className="text-sm text-muted-foreground">
                            {docs.length} comprobante{docs.length !== 1 ? 's' : ''} encontrado{docs.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowFiltros((v) => !v)}
                        >
                            <Filter className="mr-2 size-3.5" />
                            Filtros
                            {hayFiltros && (
                                <span className="ml-1.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] text-primary-foreground">
                                    !
                                </span>
                            )}
                        </Button>
                        <Button asChild size="sm">
                            <Link href="/sunat/facturas/nueva">Nueva factura</Link>
                        </Button>
                    </div>
                </div>

                {/* Filtros panel */}
                {showFiltros && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div className="flex flex-col gap-1.5">
                                    <Label>Tipo</Label>
                                    <select
                                        value={tipo}
                                        onChange={(e) => setTipo(e.target.value)}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="todos">Todos</option>
                                        <option value="facturas">Facturas</option>
                                        <option value="boletas">Boletas</option>
                                    </select>
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Estado</Label>
                                    <select
                                        value={estado}
                                        onChange={(e) => setEstado(e.target.value)}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">Todos</option>
                                        <option value="aceptado">Aceptado</option>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="enviado">Enviado</option>
                                        <option value="rechazado">Rechazado</option>
                                        <option value="borrador">Borrador</option>
                                    </select>
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Desde</Label>
                                    <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Hasta</Label>
                                    <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Cliente</Label>
                                    <Input
                                        placeholder="Buscar..."
                                        value={cliente}
                                        onChange={(e) => setCliente(e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="mt-4 flex gap-2">
                                <Button size="sm" onClick={aplicarFiltros}>
                                    <Search className="mr-2 size-3.5" />
                                    Buscar
                                </Button>
                                {hayFiltros && (
                                    <Button size="sm" variant="ghost" onClick={limpiarFiltros}>
                                        <X className="mr-2 size-3.5" />
                                        Limpiar
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Comprobantes emitidos</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {docs.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center">
                                <FileText className="size-10 text-muted-foreground/30" />
                                <p className="text-sm text-muted-foreground">No se encontraron comprobantes.</p>
                                {hayFiltros && (
                                    <Button variant="outline" size="sm" onClick={limpiarFiltros}>
                                        Limpiar filtros
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/30">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                Tipo
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                Número
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                Cliente
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                Fecha
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-medium text-muted-foreground">
                                                Total
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                                Estado
                                            </th>
                                            <th className="px-4 py-3 text-center text-xs font-medium text-muted-foreground">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {docs.map((doc) => (
                                            <tr
                                                key={`${doc.tipo_doc}-${doc.id}`}
                                                className="border-b last:border-0 hover:bg-muted/20"
                                            >
                                                <td className="px-4 py-3">
                                                    <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                                                        {TIPO_LABEL[doc.tipo_doc] ?? doc.tipo_doc}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">{doc.numero}</td>
                                                <td className="max-w-[160px] truncate px-4 py-3 text-sm">
                                                    {doc.cliente}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-muted-foreground">
                                                    {new Date(doc.fecha).toLocaleDateString('es-PE')}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-medium">
                                                    {formatPen(doc.total, doc.moneda)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={doc.estado} />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-center gap-1">
                                                        {doc.tiene_pdf && (
                                                            <a
                                                                href={`/sunat/historial/${doc.tipo_doc}/${doc.id}/pdf`}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                                                            >
                                                                <FileText className="size-3" />
                                                                PDF
                                                            </a>
                                                        )}
                                                        {doc.tiene_xml && (
                                                            <a
                                                                href={`/sunat/historial/${doc.tipo_doc}/${doc.id}/xml`}
                                                                className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                                                            >
                                                                <Download className="size-3" />
                                                                XML
                                                            </a>
                                                        )}
                                                        {doc.estado === 'aceptado' && (
                                                            <Link
                                                                href={`/sunat/nota-credito/nueva?doc_id=${doc.id}&tipo_doc=${doc.tipo_doc}`}
                                                                className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                                                            >
                                                                N/C
                                                            </Link>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SunatLayout>
    );
}
