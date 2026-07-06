import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type Flash = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
};

/**
 * Escucha los mensajes flash de Inertia (success/error/info/warning) y los
 * muestra como toasts. Se llama una vez en el layout.
 *
 * Usa una firma del contenido para no repetir el mismo toast en cada
 * re-render de Inertia (los props flash persisten en la misma visita).
 */
export function useFlashToast(): void {
    const { flash } = usePage<{ flash?: Flash }>().props;
    const ultimaFirma = useRef<string>('');

    useEffect(() => {
        if (!flash) return;

        const firma = JSON.stringify(flash);
        if (firma === ultimaFirma.current) return;
        ultimaFirma.current = firma;

        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
        if (flash.info) toast.info(flash.info);
        if (flash.warning) toast.warning(flash.warning);
    }, [flash]);
}
