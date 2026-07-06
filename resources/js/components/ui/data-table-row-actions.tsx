import * as React from 'react';
import { MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type RowAction = {
    label: string;
    icon?: React.ComponentType<{ className?: string }>;
    onSelect: () => void;
    /** Pinta el item en rojo (acciones destructivas). */
    danger?: boolean;
    /** Deshabilita el item. */
    disabled?: boolean;
    /** Inserta un separador antes de este item. */
    separatorBefore?: boolean;
};

/**
 * Menú de acciones por fila: un botón "⋮" que abre un dropdown con las
 * acciones (ver, editar, activar, eliminar...). Ordenado y consistente en
 * todas las tablas del admin.
 */
export function DataTableRowActions({ actions }: { actions: RowAction[] }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-8 data-[state=open]:bg-muted">
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">Acciones</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {actions.map((action, i) => (
                    <React.Fragment key={i}>
                        {action.separatorBefore && <DropdownMenuSeparator />}
                        <DropdownMenuItem
                            disabled={action.disabled}
                            onSelect={(e) => {
                                e.preventDefault();
                                action.onSelect();
                            }}
                            className={cn(
                                action.danger &&
                                    'text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400',
                            )}
                        >
                            {action.icon && <action.icon className="size-4" />}
                            {action.label}
                        </DropdownMenuItem>
                    </React.Fragment>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
