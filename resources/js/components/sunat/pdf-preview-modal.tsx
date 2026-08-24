import { useState } from 'react';
import { Download, Printer, X } from 'lucide-react';
import { cn } from '@/lib/utils';

const FORMATOS = [
    { value: 'a4', label: 'A4' },
    { value: 'a5', label: 'A5' },
    { value: 'ticket-80', label: '80 mm' },
    { value: 'ticket-58', label: '58 mm' },
];

type Props = {
    tipo: string;
    id: number;
    numero: string;
    initialFormat?: string;
    onClose: () => void;
};

/**
 * Visor de PDF del comprobante recién emitido. Muestra solo el documento
 * (sin bordes de modal); los controles y el cierre flotan sobre el fondo.
 */
export function PdfPreviewModal({ tipo, id, numero, initialFormat = 'a4', onClose }: Props) {
    const [format, setFormat] = useState(initialFormat);
    const src = `/sunat/documentos/${tipo}/${id}/pdf?format=${format}`;

    return (
        <div className="fixed inset-0 z-[100] flex flex-col gap-3 bg-black/70 p-3 sm:p-6" onClick={onClose}>
            {/* X para cerrar, fuera del documento */}
            <button
                type="button"
                onClick={onClose}
                className="fixed right-3 top-3 z-[110] flex size-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur transition-colors hover:bg-white/25 sm:right-5 sm:top-5"
                title="Cerrar"
            >
                <X className="size-5" />
            </button>

            {/* Controles flotantes (formato / descargar / imprimir) */}
            <div className="mx-auto flex w-full max-w-4xl items-center justify-between gap-2 pr-12 sm:pr-0" onClick={(e) => e.stopPropagation()}>
                <div className="flex rounded-xl bg-white/10 p-0.5 backdrop-blur">
                    {FORMATOS.map((f) => (
                        <button
                            key={f.value}
                            type="button"
                            onClick={() => setFormat(f.value)}
                            className={cn(
                                'rounded-lg px-2.5 py-1 text-xs font-medium transition-colors',
                                format === f.value ? 'bg-white text-neutral-900' : 'text-white/80 hover:bg-white/10',
                            )}
                        >
                            {f.label}
                        </button>
                    ))}
                </div>
                <div className="flex items-center gap-2">
                    <span className="mr-1 hidden text-xs text-white/70 sm:inline">{numero}</span>
                    <a
                        href={`${src}&download=1`}
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20"
                        title="Descargar PDF"
                    >
                        <Download className="size-4" />
                    </a>
                    <a
                        href={src}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex size-9 items-center justify-center rounded-lg bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20"
                        title="Abrir en pestaña / imprimir"
                    >
                        <Printer className="size-4" />
                    </a>
                </div>
            </div>

            {/* Solo el documento (iframe) */}
            <div className="mx-auto min-h-0 w-full max-w-4xl flex-1 overflow-hidden rounded-xl shadow-soft" onClick={(e) => e.stopPropagation()}>
                <iframe key={format} src={src} className="h-full w-full bg-white" title={`PDF ${numero}`} />
            </div>
        </div>
    );
}
