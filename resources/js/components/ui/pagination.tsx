import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type PaginationLink = { url: string | null; label: string; active: boolean };

type Props = {
    links: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
    /** Slot a la izquierda (ej. selector "por página"). */
    left?: ReactNode;
    className?: string;
};

/**
 * Paginación circular reutilizable para TODAS las tablas del admin.
 * Consume el array `links` de la paginación de Laravel. Botones redondos,
 * flechas para anterior/siguiente, y resumen "x–y de N". 100% responsivo.
 */
export function Pagination({ links, from, to, total, left, className }: Props) {
    const hasPages = Array.isArray(links) && links.length > 3; // más que prev + 1 + next

    if (!hasPages && !left && total === undefined) return null;

    const base =
        'inline-flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors';

    return (
        <div className={cn('flex flex-col items-center justify-between gap-3 sm:flex-row', className)}>
            <div className="flex shrink-0 items-center gap-3 text-sm text-muted-foreground">
                {total !== undefined && (
                    <span className="whitespace-nowrap tabular-nums">
                        {(from ?? 0).toLocaleString('es-PE')}–{(to ?? 0).toLocaleString('es-PE')} de{' '}
                        <span className="font-semibold text-foreground">{total.toLocaleString('es-PE')}</span>
                    </span>
                )}
                {left}
            </div>

            {hasPages && (
                <nav className="flex flex-nowrap items-center justify-center gap-1.5 overflow-x-auto">
                    {links.map((link, i) => {
                        const content =
                            i === 0 ? (
                                <ChevronLeft className="size-4" />
                            ) : i === links.length - 1 ? (
                                <ChevronRight className="size-4" />
                            ) : (
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            );

                        if (!link.url) {
                            return (
                                <span key={i} className={cn(base, 'text-muted-foreground opacity-40')}>
                                    {content}
                                </span>
                            );
                        }

                        return (
                            <Link
                                key={i}
                                href={link.url}
                                preserveScroll
                                preserveState
                                className={cn(
                                    base,
                                    link.active
                                        ? 'bg-primary text-primary-foreground shadow-soft'
                                        : 'bg-card text-foreground shadow-soft hover:bg-accent',
                                )}
                            >
                                {content}
                            </Link>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}
