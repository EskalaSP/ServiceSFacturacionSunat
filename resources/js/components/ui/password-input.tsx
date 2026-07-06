import * as React from 'react';
import { Eye, EyeOff } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type PasswordInputProps = Omit<React.ComponentProps<'input'>, 'type'>;

/**
 * Input de contraseña con toggle de visibilidad (ojito).
 * Se comporta como el <Input> normal — mismos props — pero añade un
 * botón integrado a la derecha para alternar entre password/text.
 */
export const PasswordInput = React.forwardRef<HTMLInputElement, PasswordInputProps>(
    ({ className, disabled, ...props }, ref) => {
        const [visible, setVisible] = React.useState(false);

        return (
            <div className="relative">
                <Input
                    {...props}
                    ref={ref}
                    disabled={disabled}
                    type={visible ? 'text' : 'password'}
                    className={cn('pr-10', className)}
                />
                <button
                    type="button"
                    onClick={() => setVisible((v) => !v)}
                    aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    tabIndex={-1}
                    className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted-foreground transition-colors hover:text-foreground focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    disabled={disabled}
                >
                    {visible ? (
                        <EyeOff className="h-4 w-4" aria-hidden="true" />
                    ) : (
                        <Eye className="h-4 w-4" aria-hidden="true" />
                    )}
                </button>
            </div>
        );
    }
);

PasswordInput.displayName = 'PasswordInput';
