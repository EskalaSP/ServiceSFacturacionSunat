import { Check, X } from 'lucide-react';

/** Reglas de la política de contraseña (debe coincidir con App\Rules\PasswordPolicy). */
export const passwordChecks = [
    { label: 'Mínimo 6 caracteres', test: (v: string) => v.length >= 6 },
    { label: 'Una letra mayúscula', test: (v: string) => /[A-Z]/.test(v) },
    { label: 'Una letra minúscula', test: (v: string) => /[a-z]/.test(v) },
    { label: 'Un número', test: (v: string) => /[0-9]/.test(v) },
    { label: 'Un símbolo (! @ # $ …)', test: (v: string) => /[^A-Za-z0-9]/.test(v) },
];

/** ¿La contraseña cumple TODAS las reglas? */
export const passwordIsValid = (v: string) => passwordChecks.every((c) => c.test(v));

/**
 * Lista de requisitos que se validan en tiempo real mientras el usuario escribe.
 * Cada regla se marca en verde (✓) al cumplirse.
 */
export function PasswordRequirements({ value }: { value: string }) {
    return (
        <ul className="mt-1 grid gap-1">
            {passwordChecks.map((c) => {
                const ok = c.test(value);
                return (
                    <li
                        key={c.label}
                        className={`flex items-center gap-1.5 text-xs transition-colors ${
                            ok ? 'text-[#00BA5D]' : 'text-muted-foreground'
                        }`}
                    >
                        <span
                            className={`flex size-3.5 shrink-0 items-center justify-center rounded-full ${
                                ok ? 'bg-[#00BA5D]/15' : 'bg-muted'
                            }`}
                        >
                            {ok ? <Check className="size-2.5" /> : <X className="size-2.5 opacity-50" />}
                        </span>
                        {c.label}
                    </li>
                );
            })}
        </ul>
    );
}
