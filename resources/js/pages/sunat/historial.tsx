import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Filter, Search, X } from 'lucide-react';
import { DocumentosTable } from '@/components/sunat/documentos-table';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import SunatLayout from '@/layouts/sunat-layout';
import type { DocumentoSunat, SharedData } from '@/types';

type Filtros = {
    tipo: string;
    estado: string;
    desde: string;
    hasta: string;
    cliente: string;
};

const TIPO_FILTRO_LABEL: Record<string, string> = {
    facturas: 'Facturas',
    boletas: 'Boletas',
    'notas-credito': 'Notas de crédito',
    'notas-debito': 'Notas de débito',
    guias: 'Guías de remisión',
    retenciones: 'Retenciones',
    percepciones: 'Percepciones',
};
const ESTADO_FILTRO_LABEL: Record<string, string> = {
    aceptado: 'Aceptado',
    pendiente: 'Pendiente',
    enviado: 'Enviado',
    rechazado: 'Rechazado',
    anulado: 'Anulado',
    borrador: 'Borrador',
};

type Props = {
    documentos: { data: DocumentoSunat[]; total: number };
    filtros: Filtros;
    tenant: { environment: string };
};

export default function Historial({ documentos, filtros }: Props) {
    const { props } = usePage<SharedData>();
    const esSimple = props.empresa?.rol === 'simple';
    const [tipo, setTipo] = useState(filtros.tipo ?? 'todos');
    const [estado, setEstado] = useState(filtros.estado ?? '');
    const [desde, setDesde] = useState(filtros.desde ?? '');
    const [hasta, setHasta] = useState(filtros.hasta ?? '');
    const [cliente, setCliente] = useState(filtros.cliente ?? '');
    const [showFiltros, setShowFiltros] = useState(false);

    const docs = documentos.data ?? [];

    function aplicarFiltros() {
        router.get(
            '/sunat/historial',
            { tipo, estado, desde, hasta, cliente },
            { preserveState: true },
        );
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

    function quitarFiltro(key: keyof Filtros) {
        const params: Filtros = { tipo, estado, desde, hasta, cliente };
        params[key] = key === 'tipo' ? 'todos' : '';
        setTipo(params.tipo);
        setEstado(params.estado);
        setDesde(params.desde);
        setHasta(params.hasta);
        setCliente(params.cliente);
        router.get('/sunat/historial', params, { preserveState: true });
    }

    const chips = [
        tipo !== 'todos'
            ? {
                  key: 'tipo' as const,
                  texto: `Tipo: ${TIPO_FILTRO_LABEL[tipo] ?? tipo}`,
              }
            : null,
        estado
            ? {
                  key: 'estado' as const,
                  texto: `Estado: ${ESTADO_FILTRO_LABEL[estado] ?? estado}`,
              }
            : null,
        desde ? { key: 'desde' as const, texto: `Desde: ${desde}` } : null,
        hasta ? { key: 'hasta' as const, texto: `Hasta: ${hasta}` } : null,
        cliente
            ? { key: 'cliente' as const, texto: `Cliente: ${cliente}` }
            : null,
    ].filter((c): c is { key: keyof Filtros; texto: string } => c !== null);

    return (
        <SunatLayout>
            <Head title="Historial de Comprobantes" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Historial
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {docs.length} comprobante
                            {docs.length !== 1 ? 's' : ''} encontrado
                            {docs.length !== 1 ? 's' : ''}
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
                        {!esSimple && (
                            <Button asChild size="sm">
                                <Link href="/sunat/facturas/nueva">
                                    Nueva factura
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Filtros panel (servidor) */}
                {showFiltros && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div className="flex flex-col gap-1.5">
                                    <Label>Tipo</Label>
                                    <Combobox
                                        value={tipo}
                                        onChange={(v) => setTipo(v)}
                                        searchable
                                        options={[
                                            { value: 'todos', label: 'Todos' },
                                            {
                                                value: 'facturas',
                                                label: 'Facturas',
                                            },
                                            {
                                                value: 'boletas',
                                                label: 'Boletas',
                                            },
                                            {
                                                value: 'notas-credito',
                                                label: 'Notas de crédito',
                                            },
                                            {
                                                value: 'notas-debito',
                                                label: 'Notas de débito',
                                            },
                                            {
                                                value: 'guias',
                                                label: 'Guías de remisión',
                                            },
                                            {
                                                value: 'retenciones',
                                                label: 'Retenciones',
                                            },
                                            {
                                                value: 'percepciones',
                                                label: 'Percepciones',
                                            },
                                        ]}
                                        className="h-9 rounded-md"
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Estado</Label>
                                    <Combobox
                                        value={estado}
                                        onChange={(v) => setEstado(v)}
                                        placeholder="Todos"
                                        options={[
                                            { value: '', label: 'Todos' },
                                            {
                                                value: 'aceptado',
                                                label: 'Aceptado',
                                            },
                                            {
                                                value: 'pendiente',
                                                label: 'Pendiente',
                                            },
                                            {
                                                value: 'enviado',
                                                label: 'Enviado',
                                            },
                                            {
                                                value: 'rechazado',
                                                label: 'Rechazado',
                                            },
                                            {
                                                value: 'anulado',
                                                label: 'Anulado',
                                            },
                                            {
                                                value: 'borrador',
                                                label: 'Borrador',
                                            },
                                        ]}
                                        className="h-9 rounded-md"
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Desde</Label>
                                    <Input
                                        type="date"
                                        value={desde}
                                        onChange={(e) =>
                                            setDesde(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Hasta</Label>
                                    <Input
                                        type="date"
                                        value={hasta}
                                        onChange={(e) =>
                                            setHasta(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Cliente</Label>
                                    <Input
                                        placeholder="Buscar..."
                                        value={cliente}
                                        onChange={(e) =>
                                            setCliente(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="mt-4 flex gap-2">
                                <Button size="sm" onClick={aplicarFiltros}>
                                    <Search className="mr-2 size-3.5" /> Buscar
                                </Button>
                                {hayFiltros && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={limpiarFiltros}
                                    >
                                        <X className="mr-2 size-3.5" /> Limpiar
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Chips de filtros activos */}
                {chips.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        {chips.map((c) => (
                            <span
                                key={c.key}
                                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-3 py-1 text-xs text-foreground"
                            >
                                {c.texto}
                                <button
                                    type="button"
                                    onClick={() => quitarFiltro(c.key)}
                                    className="text-muted-foreground transition-colors hover:text-destructive"
                                    title="Quitar filtro"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        ))}
                        <button
                            type="button"
                            onClick={limpiarFiltros}
                            className="text-xs font-medium text-muted-foreground hover:text-foreground hover:underline"
                        >
                            Limpiar todo
                        </button>
                    </div>
                )}

                <DocumentosTable
                    documentos={docs}
                    searchPlaceholder="Buscar en resultados..."
                    emptyMessage="No se encontraron comprobantes."
                />
            </div>
        </SunatLayout>
    );
}
