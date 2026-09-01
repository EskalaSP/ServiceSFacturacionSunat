import type { SunatStatus } from '@/types';

const CONFIG: Record<SunatStatus, { label: string; className: string }> = {
    aceptado:  { label: 'Aceptado',  className: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' },
    rechazado: { label: 'Rechazado', className: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' },
    enviado:   { label: 'Enviando…', className: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' },
    pendiente: { label: 'Pendiente', className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300' },
    borrador:  { label: 'Borrador',  className: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' },
    anulacion_en_proceso: { label: 'Anulación en proceso', className: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300' },
    anulado:   { label: 'Anulado',   className: 'bg-neutral-200 text-neutral-700 line-through dark:bg-neutral-800 dark:text-neutral-300' },
};

export function StatusBadge({ status }: { status: SunatStatus }) {
    const cfg = CONFIG[status] ?? CONFIG.borrador;
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${cfg.className}`}>
            {cfg.label}
        </span>
    );
}
