import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/**
 * Toaster global (sonner) integrado con el tema de la app.
 * Se monta una sola vez en el layout. Los toasts se disparan con
 * `toast.success(...)`, `toast.error(...)`, etc. desde cualquier componente.
 */
export function Toaster({ ...props }: ToasterProps) {
    const { resolvedAppearance } = useAppearance();

    return (
        <Sonner
            theme={resolvedAppearance}
            position="top-right"
            richColors
            closeButton
            duration={4000}
            className="toaster group"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--border-radius': '0.75rem',
                } as React.CSSProperties
            }
            toastOptions={{
                classNames: {
                    toast: 'rounded-xl border shadow-lg',
                    title: 'font-semibold text-sm',
                    description: 'text-xs opacity-90',
                },
            }}
            {...props}
        />
    );
}
