import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, FileText, LogOut, Settings } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { SharedData } from '@/types';

type NavItem = {
    label: string;
    href: string;
    match: string;
    /** Permiso requerido para ver el item; si falta, no se muestra. */
    can?: string;
    /** Visible si el usuario tiene AL MENOS uno de estos permisos. */
    canAny?: string[];
    /** 'primary' = pill visible; 'more' = dentro del menú "Más". */
    group?: 'primary' | 'more';
};

const NAV_ITEMS: NavItem[] = [
    { label: 'Factura', href: '/sunat/facturas/nueva', match: '/sunat/facturas', can: 'factura.emitir', group: 'primary' },
    { label: 'Boleta', href: '/sunat/boletas/nueva', match: '/sunat/boletas', can: 'boleta.emitir', group: 'primary' },
    { label: 'Historial', href: '/sunat/historial', match: '/sunat/historial', group: 'primary' },
    { label: 'Clientes', href: '/sunat/clientes', match: '/sunat/clientes', can: 'cliente.gestionar', group: 'primary' },
    { label: 'Mi equipo', href: '/sunat/equipo', match: '/sunat/equipo', can: 'equipo.gestionar', group: 'primary' },

    { label: 'Cotizaciones', href: '/sunat/cotizaciones', match: '/sunat/cotizaciones', can: 'cotizacion.emitir', group: 'more' },
    { label: 'Nota de venta', href: '/sunat/nota-venta/nueva', match: '/sunat/nota-venta', can: 'nota_venta.emitir', group: 'more' },
    { label: 'Nota de Crédito', href: '/sunat/nota-credito/nueva', match: '/sunat/nota-credito', can: 'nota_credito.emitir', group: 'more' },
    { label: 'Nota de Débito', href: '/sunat/nota-debito/nueva', match: '/sunat/nota-debito', can: 'nota_debito.emitir', group: 'more' },
    { label: 'Guías de remisión', href: '/sunat/guias/nueva', match: '/sunat/guias', canAny: ['guia_remitente.emitir', 'guia_transportista.emitir'], group: 'more' },
    { label: 'Resumen diario', href: '/sunat/resumenes/nueva', match: '/sunat/resumenes', can: 'resumen.emitir', group: 'more' },
    { label: 'Anulación', href: '/sunat/anulaciones/nueva', match: '/sunat/anulaciones', can: 'anulacion.emitir', group: 'more' },
    { label: 'Retención', href: '/sunat/retenciones/nueva', match: '/sunat/retenciones', can: 'retencion.emitir', group: 'more' },
    { label: 'Percepción', href: '/sunat/percepciones/nueva', match: '/sunat/percepciones', can: 'percepcion.emitir', group: 'more' },
    { label: 'Reversión', href: '/sunat/reversiones/nueva', match: '/sunat/reversiones', can: 'reversion.emitir', group: 'more' },
    { label: 'Reportes', href: '/sunat/reportes', match: '/sunat/reportes', can: 'reporte.ver', group: 'more' },
    { label: 'Consultar SUNAT', href: '/sunat/consulta-cpe', match: '/sunat/consulta-cpe', can: 'consulta.cpe', group: 'more' },
    { label: 'Exportar (ZIP)', href: '/sunat/exportar', match: '/sunat/exportar', can: 'exportar', group: 'more' },
    { label: 'Series', href: '/sunat/series', match: '/sunat/series', can: 'serie.gestionar', group: 'more' },
    { label: 'SIRE (RCE)', href: '/sunat/sire', match: '/sunat/sire', can: 'sire.gestionar', group: 'more' },
    { label: 'Mi API Key', href: '/sunat/mi-api-key', match: '/sunat/mi-api-key', can: 'apikey.ver', group: 'more' },
];

export function SunatTopNav() {
    const { url, props } = usePage<SharedData>();
    const user = props.auth?.user;
    const tenant = props.tenant;
    const empresa = props.empresa;

    const has = (ability: string) => empresa?.can?.includes(ability) ?? false;
    const can = (ability?: string) => !ability || has(ability);
    const visible = (item: NavItem) => can(item.can) && (!item.canAny || item.canAny.some(has));

    const items = NAV_ITEMS.filter(visible);
    const primary = items.filter((i) => i.group !== 'more');
    const more = items.filter((i) => i.group === 'more');

    const displayName = user?.name ?? 'Usuario';
    const initial = displayName.trim().charAt(0).toUpperCase() || 'U';

    const disponibles = empresa?.disponibles ?? [];
    const puedeCambiarEmpresa = disponibles.length > 1;

    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [moreOpen, setMoreOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const moreRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) setUserMenuOpen(false);
            if (moreRef.current && !moreRef.current.contains(e.target as Node)) setMoreOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const handleLogout = () => router.post('/logout');
    const cambiarEmpresa = (id: string) => router.put('/sunat/empresa-activa', { tenant_id: Number(id) }, { preserveScroll: true });

    const pill = (item: NavItem) => {
        const isActive = url.startsWith(item.match);
        return (
            <Link key={item.href} href={item.href} className="relative shrink-0 rounded-full px-3.5 py-1.5 text-sm transition-colors sm:px-4">
                {isActive && <span className="absolute inset-0 rounded-full bg-accent" />}
                <span className={`relative z-10 whitespace-nowrap ${isActive ? 'font-medium text-foreground' : 'text-muted-foreground hover:text-foreground'}`}>
                    {item.label}
                </span>
            </Link>
        );
    };

    return (
        <header className="sticky top-0 z-50 border-b border-border/45 bg-background/88 shadow-soft backdrop-blur-md supports-[backdrop-filter]:bg-background/72">
            <div className="mx-auto flex max-w-[1680px] flex-col gap-3 px-4 py-3 sm:px-6 sm:py-3.5">
                <div className="flex w-full items-center gap-3">
                    <Link href="/sunat/facturas/nueva" className="flex shrink-0 items-center gap-2.5 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent">
                            <FileText className="h-5 w-5 text-primary" />
                        </div>
                        <div className="hidden sm:block">
                            <div className="text-sm font-semibold leading-tight tracking-tight text-foreground">Facturación</div>
                            {tenant && (
                                <div className="text-[10px] leading-tight text-muted-foreground">
                                    {tenant.ruc} · {tenant.environment === 'beta' ? 'Beta' : 'Producción'}
                                </div>
                            )}
                        </div>
                    </Link>

                    {puedeCambiarEmpresa && tenant && (
                        <label className="hidden items-center gap-2 sm:flex" title="Cambiar de empresa">
                            <span className="sr-only">Empresa activa</span>
                            <select
                                value={tenant.id}
                                onChange={(e) => cambiarEmpresa(e.target.value)}
                                className="max-w-[220px] truncate rounded-full border border-border/60 bg-card px-3 py-1.5 text-xs text-foreground outline-none transition-colors hover:bg-secondary focus-visible:ring-2 focus-visible:ring-primary/30"
                            >
                                {disponibles.map((e) => (
                                    <option key={e.id} value={e.id}>{e.razon_social} · {e.ruc}</option>
                                ))}
                            </select>
                        </label>
                    )}

                    {/* Nav — desktop */}
                    <div className="hidden flex-1 justify-center md:flex">
                        <nav className="flex items-center gap-0.5 rounded-full border border-border/50 bg-card/50 px-1 py-1" aria-label="Módulos SUNAT">
                            {primary.map(pill)}
                            {more.length > 0 && (
                                <div className="relative" ref={moreRef}>
                                    <button
                                        type="button"
                                        onClick={() => setMoreOpen((o) => !o)}
                                        className="flex shrink-0 items-center gap-1 rounded-full px-3.5 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground sm:px-4"
                                    >
                                        Más <ChevronDown className="size-3.5" />
                                    </button>
                                    {moreOpen && (
                                        <div className="absolute left-1/2 top-[calc(100%+0.5rem)] z-[60] w-56 -translate-x-1/2 rounded-xl border border-border/60 bg-card p-1 shadow-soft">
                                            {more.map((item) => (
                                                <Link
                                                    key={item.href}
                                                    href={item.href}
                                                    onClick={() => setMoreOpen(false)}
                                                    className={`block rounded-lg px-3 py-2 text-sm transition-colors hover:bg-secondary ${url.startsWith(item.match) ? 'font-medium text-foreground' : 'text-muted-foreground'}`}
                                                >
                                                    {item.label}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </nav>
                    </div>

                    <div className="ml-auto flex shrink-0 items-center gap-2 sm:gap-3 md:ml-0">
                        {can('config.editar') && (
                            <Link href="/sunat/configuracion" className="flex h-9 w-9 items-center justify-center rounded-full border border-border/50 bg-card text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Configuración SUNAT">
                                <Settings className="h-4 w-4" />
                            </Link>
                        )}

                        <div className="relative" ref={menuRef}>
                            <button
                                type="button"
                                onClick={() => setUserMenuOpen((o) => !o)}
                                className="hidden items-center gap-2 rounded-full border border-border/50 bg-card py-1 pl-1 pr-2.5 transition-colors hover:bg-secondary sm:flex sm:pr-3"
                            >
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent text-[11px] font-semibold text-foreground">{initial}</span>
                                <div className="hidden text-left leading-tight lg:block">
                                    <div className="max-w-[140px] truncate text-xs font-medium text-foreground">{displayName}</div>
                                    {user?.email && <div className="max-w-[140px] truncate text-[10px] text-muted-foreground">{user.email}</div>}
                                </div>
                                <ChevronDown className="hidden h-3.5 w-3.5 shrink-0 text-muted-foreground lg:block" />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 top-[calc(100%+0.5rem)] z-[60] w-44 rounded-xl border border-border/60 bg-card py-1 shadow-soft">
                                    <button type="button" onClick={handleLogout} className="flex w-full items-center gap-2 px-3 py-2 text-xs text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                        <LogOut className="h-3.5 w-3.5" />
                                        Cerrar sesión
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Selector de empresa — mobile */}
                {puedeCambiarEmpresa && tenant && (
                    <select
                        value={tenant.id}
                        onChange={(e) => cambiarEmpresa(e.target.value)}
                        className="w-full rounded-lg border border-border/60 bg-card px-3 py-2 text-xs text-foreground outline-none sm:hidden"
                    >
                        {disponibles.map((e) => (
                            <option key={e.id} value={e.id}>{e.razon_social} · {e.ruc}</option>
                        ))}
                    </select>
                )}

                {/* Nav — mobile (todos los items en scroll) */}
                <div className="-mx-1 overflow-x-auto pb-0.5 md:hidden">
                    <nav className="flex w-max min-w-full items-center gap-0.5 rounded-full border border-border/50 bg-card/50 px-1 py-1" aria-label="Módulos SUNAT">
                        {items.map(pill)}
                    </nav>
                </div>
            </div>
        </header>
    );
}
