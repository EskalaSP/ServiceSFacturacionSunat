import * as React from 'react';
import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmOptions = {
    title?: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    /** 'danger' pinta el botón en rojo (borrar). 'default' usa el primario. */
    variant?: 'default' | 'danger';
};

type ConfirmContextValue = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = React.createContext<ConfirmContextValue | null>(null);

/**
 * Provider de confirmaciones. Envuelve la app (en el layout) y expone
 * `useConfirm()` que devuelve una función async:
 *
 *   const confirm = useConfirm();
 *   if (await confirm({ title: '¿Eliminar?', variant: 'danger' })) { ... }
 */
export function ConfirmDialogProvider({ children }: { children: React.ReactNode }) {
    const [open, setOpen] = React.useState(false);
    const [options, setOptions] = React.useState<ConfirmOptions>({});
    const resolver = React.useRef<(value: boolean) => void>(() => {});

    const confirm = React.useCallback<ConfirmContextValue>((opts) => {
        setOptions(opts);
        setOpen(true);
        return new Promise<boolean>((resolve) => {
            resolver.current = resolve;
        });
    }, []);

    const cerrar = (valor: boolean) => {
        setOpen(false);
        resolver.current(valor);
    };

    const esDanger = options.variant === 'danger';

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            <Dialog open={open} onOpenChange={(o) => !o && cerrar(false)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {esDanger && <AlertTriangle className="size-5 text-red-500" />}
                            {options.title ?? '¿Confirmar acción?'}
                        </DialogTitle>
                        {options.description && (
                            <DialogDescription>{options.description}</DialogDescription>
                        )}
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-2">
                        <Button variant="outline" onClick={() => cerrar(false)}>
                            {options.cancelText ?? 'Cancelar'}
                        </Button>
                        <Button
                            variant={esDanger ? 'destructive' : 'default'}
                            onClick={() => cerrar(true)}
                        >
                            {options.confirmText ?? 'Confirmar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ConfirmContext.Provider>
    );
}

export function useConfirm(): ConfirmContextValue {
    const ctx = React.useContext(ConfirmContext);
    if (!ctx) {
        throw new Error('useConfirm debe usarse dentro de <ConfirmDialogProvider>');
    }
    return ctx;
}
