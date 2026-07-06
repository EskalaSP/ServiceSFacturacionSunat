import { Monitor, Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

/**
 * Selector de tema compacto (solo iconos) para el header: Claro / Oscuro / Sistema.
 */
export function HeaderThemeToggle() {
    const { appearance, updateAppearance } = useAppearance();

    const opciones: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Modo claro' },
        { value: 'dark', icon: Moon, label: 'Modo oscuro' },
        { value: 'system', icon: Monitor, label: 'Según el sistema' },
    ];

    return (
        <div className="bg-muted inline-flex items-center gap-0.5 rounded-full p-0.5">
            {opciones.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => updateAppearance(value)}
                    title={label}
                    aria-label={label}
                    aria-pressed={appearance === value}
                    className={cn(
                        'flex size-7 items-center justify-center rounded-full transition-colors',
                        appearance === value
                            ? 'bg-card text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Icon className="size-4" />
                </button>
            ))}
        </div>
    );
}
