import { ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type MultiSelectOption = { value: string; label: string };

type Props = {
    options: MultiSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    className?: string;
};

/** Selector múltiple con casillas; resume la selección en el botón. */
export function MultiSelect({
    options,
    value,
    onChange,
    placeholder = 'Todos',
    className,
}: Props) {
    const seleccion = options.filter((o) => value.includes(o.value));
    const resumen =
        seleccion.length === 0
            ? placeholder
            : seleccion.length <= 2
              ? seleccion.map((o) => o.label).join(', ')
              : `${seleccion.length} seleccionados`;

    function alternar(v: string, marcado: boolean) {
        onChange(marcado ? [...value, v] : value.filter((x) => x !== v));
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={`h-9 w-full justify-between font-normal ${className ?? ''}`}
                >
                    <span className="truncate">{resumen}</span>
                    <ChevronDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-56">
                {options.map((o) => (
                    <DropdownMenuCheckboxItem
                        key={o.value}
                        checked={value.includes(o.value)}
                        onCheckedChange={(m) => alternar(o.value, m === true)}
                        onSelect={(e) => e.preventDefault()}
                    >
                        {o.label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
