import { cn } from '@/lib/utils';

type SwitchProps = {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    id?: string;
    'aria-labelledby'?: string;
};

/**
 * Toggle accesible sin dependencias externas (role="switch").
 * Usa los tokens del tema (bg-primary / bg-input).
 */
export function Switch({ checked, onCheckedChange, disabled, id, ...aria }: SwitchProps) {
    return (
        <button
            {...aria}
            type="button"
            role="switch"
            id={id}
            aria-checked={checked}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'focus-visible:ring-ring relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                checked ? 'bg-primary' : 'bg-input',
            )}
        >
            <span
                className={cn(
                    'inline-block size-5 transform rounded-full bg-white shadow transition-transform',
                    checked ? 'translate-x-5' : 'translate-x-0.5',
                )}
            />
        </button>
    );
}
