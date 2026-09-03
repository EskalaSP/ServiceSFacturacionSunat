import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DocumentosTable } from '@/components/sunat/documentos-table';
import { Button } from '@/components/ui/button';
import SunatLayout from '@/layouts/sunat-layout';
import type { DocumentoSunat, SharedData } from '@/types';

type Props = {
    titulo: string;
    subtitulo?: string;
    nuevo: { href: string; label: string };
    documentos: DocumentoSunat[];
    /** Oculta la columna Tipo (listados de un solo tipo). Default: true */
    ocultarTipo?: boolean;
    emptyMessage?: string;
    searchPlaceholder?: string;
};

/** Listado genérico de comprobantes por tipo, con botón para emitir uno nuevo. */
export default function DocumentosIndex({ titulo, subtitulo, nuevo, documentos, ocultarTipo = true, emptyMessage, searchPlaceholder }: Props) {
    const { props } = usePage<SharedData>();
    const esSimple = props.empresa?.rol === 'simple';

    return (
        <SunatLayout>
            <Head title={titulo} />

            <div className="mx-auto max-w-6xl">
                <div className="mb-6">
                    <h1 className="text-xl font-semibold tracking-tight">{titulo}</h1>
                    {subtitulo && <p className="text-sm text-muted-foreground">{subtitulo}</p>}
                </div>

                {!esSimple && (
                    <div className="mb-4 flex justify-end">
                        <Button asChild className="gap-2 rounded-xl">
                            <Link href={nuevo.href}><Plus className="size-4" /> {nuevo.label}</Link>
                        </Button>
                    </div>
                )}

                <DocumentosTable
                    documentos={documentos}
                    ocultarTipo={ocultarTipo}
                    emptyMessage={emptyMessage ?? 'Aún no has emitido comprobantes de este tipo.'}
                    searchPlaceholder={searchPlaceholder ?? 'Buscar por número o cliente...'}
                />
            </div>
        </SunatLayout>
    );
}
