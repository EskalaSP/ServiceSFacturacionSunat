import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type ComboboxOption = { value: string; label: string };

type Props = {
    options: ComboboxOption[];
    value?: string;
    onChange: (value: string) => void;
    placeholder?: string;
    /** Muestra un buscador dentro del desplegable (para listas largas). */
    searchable?: boolean;
    /** Umbral automático: activa el buscador si hay ≥ N opciones. Default 8. */
    searchThreshold?: number;
    disabled?: boolean;
    className?: string;
    /** Texto cuando el buscador no encuentra nada. */
    emptyText?: string;
};

// Radix Select no admite value="" (lo reserva para "sin selección"). Mapeamos
// el vacío a un centinela para poder tener una opción "Todos" con value "".
const EMPTY = '__all__';
const toRadix = (v?: string) => (v === '' || v == null ? EMPTY : v);
const fromRadix = (v: string) => (v === EMPTY ? '' : v);

/**
 * Select bonito y consistente para todo el proyecto (sobre Radix Select).
 * Con buscador opcional (automático en listas largas). 100% responsivo,
 * theme-aware, con la apariencia de los inputs del sistema.
 */
export function Combobox({
    options,
    value,
    onChange,
    placeholder = 'Seleccionar…',
    searchable,
    searchThreshold = 8,
    disabled,
    className,
    emptyText = 'Sin resultados',
}: Props) {
    const [q, setQ] = useState('');
    const withSearch = searchable ?? options.length >= searchThreshold;

    const filtered = useMemo(() => {
        if (!withSearch || !q.trim()) return options;
        const t = q.toLowerCase();
        return options.filter((o) => o.label.toLowerCase().includes(t));
    }, [options, q, withSearch]);

    return (
        <Select
            value={toRadix(value)}
            onValueChange={(v) => onChange(fromRadix(v))}
            disabled={disabled}
            onOpenChange={(open) => !open && setQ('')}
        >
            <SelectTrigger
                className={cn(
                    'form-select-trigger w-full border-transparent bg-input font-medium shadow-none data-[state=open]:border-ring',
                    className,
                )}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent className="max-h-72">
                {withSearch && (
                    <div className="sticky top-0 z-10 bg-popover p-1">
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2 top-2 size-3.5 text-muted-foreground" />
                            <input
                                autoFocus
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                onKeyDown={(e) => e.stopPropagation()}
                                placeholder="Buscar…"
                                className="w-full rounded-md bg-muted py-1.5 pl-7 pr-2 text-sm outline-none"
                            />
                        </div>
                    </div>
                )}
                {filtered.length > 0 ? (
                    filtered.map((o) => (
                        <SelectItem key={o.value === '' ? EMPTY : o.value} value={toRadix(o.value)}>
                            {o.label}
                        </SelectItem>
                    ))
                ) : (
                    <div className="px-2 py-3 text-center text-sm text-muted-foreground">{emptyText}</div>
                )}
            </SelectContent>
        </Select>
    );
}
