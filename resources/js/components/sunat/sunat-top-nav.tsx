import { Link, router, usePage } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import { Settings, FileText, ChevronDown, LogOut } from 'lucide-react';
import type { SharedData } from '@/types';

const NAV_ITEMS = [
    { label: 'Nueva Factura',   href: '/sunat/facturas/nueva',       match: '/sunat/facturas' },
    { label: 'Cotizaciones',    href: '/sunat/cotizaciones',          match: '/sunat/cotizaciones' },
    { label: 'Historial',       href: '/sunat/historial',             match: '/sunat/historial' },
    { label: 'Nota de Crédito', href: '/sunat/nota-credito/nueva',   match: '/sunat/nota-credito' },
    { label: 'Nota de Débito',  href: '/sunat/nota-debito/nueva',    match: '/sunat/nota-debito' },
    { label: 'Clientes',        href: '/sunat/clientes',              match: '/sunat/clientes' },
    { label: 'Mi API Key',      href: '/sunat/mi-api-key',            match: '/sunat/mi-api-key' },
    { label: 'Configuración',   href: '/sunat/configuracion',         match: '/sunat/configuracion' },
] as const;

export function SunatTopNav() {
    const { url, props } = usePage<SharedData & { tenant: { ruc: string; razon_social: string; environment: string } | null }>();
    const user = props.auth?.user as { name?: string; email?: string } | undefined;
    const tenant = props.tenant;

    const displayName = user?.name ?? 'Usuario';
    const initial = displayName.trim().charAt(0).toUpperCase() || 'U';

    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!userMenuOpen) return;
        const handler = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
                setUserMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [userMenuOpen]);

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <header className="sticky top-0 z-50 border-b border-border/45 bg-background/88 shadow-soft backdrop-blur-md supports-[backdrop-filter]:bg-background/72">
            <div className="mx-auto flex max-w-[1680px] flex-col gap-3 px-4 py-3 sm:px-6 sm:py-3.5">

                {/* ── Fila principal ── */}
                <div className="flex w-full items-center gap-3">

                    {/* Logo / Marca */}
                    <Link
                        href="/sunat/facturas/nueva"
                        className="flex shrink-0 items-center gap-2.5 rounded-lg outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-primary/30"
                    >
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent">
                            <FileText className="h-5 w-5 text-primary" />
                        </div>
                        <div className="hidden sm:block">
                            <div className="text-sm font-semibold tracking-tight text-foreground leading-tight">
                                Facturación
                            </div>
                            {tenant && (
                                <div className="text-[10px] text-muted-foreground leading-tight">
                                    {tenant.ruc} · {tenant.environment === 'beta' ? 'Beta' : 'Producción'}
                                </div>
                            )}
                        </div>
                    </Link>

                    {/* Nav Pills — desktop */}
                    <div className="hidden flex-1 justify-center md:flex">
                        <nav
                            className="flex items-center gap-0.5 rounded-full border border-border/50 bg-card/50 px-1 py-1"
                            aria-label="Módulos SUNAT"
                        >
                            {NAV_ITEMS.map((item) => {
                                const isActive = url.startsWith(item.match);
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className="relative shrink-0 rounded-full px-3.5 py-1.5 text-sm transition-colors sm:px-4"
                                    >
                                        {isActive && (
                                            <span className="absolute inset-0 rounded-full bg-accent" />
                                        )}
                                        <span
                                            className={`relative z-10 whitespace-nowrap ${
                                                isActive
                                                    ? 'font-medium text-foreground'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            {item.label}
                                        </span>
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>

                    {/* Derecha: settings + usuario */}
                    <div className="ml-auto flex shrink-0 items-center gap-2 sm:gap-3 md:ml-0">
                        <Link
                            href="/sunat/configuracion"
                            className="flex h-9 w-9 items-center justify-center rounded-full border border-border/50 bg-card text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                            title="Configuración SUNAT"
                        >
                            <Settings className="h-4 w-4" />
                        </Link>

                        {/* Avatar + dropdown */}
                        <div className="relative" ref={menuRef}>
                            <button
                                type="button"
                                onClick={() => setUserMenuOpen((o) => !o)}
                                className="hidden items-center gap-2 rounded-full border border-border/50 bg-card py-1 pl-1 pr-2.5 transition-colors hover:bg-secondary sm:flex sm:pr-3"
                            >
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent text-[11px] font-semibold text-foreground">
                                    {initial}
                                </span>
                                <div className="hidden text-left leading-tight lg:block">
                                    <div className="max-w-[140px] truncate text-xs font-medium text-foreground">
                                        {displayName}
                                    </div>
                                    {user?.email && (
                                        <div className="max-w-[140px] truncate text-[10px] text-muted-foreground">
                                            {user.email}
                                        </div>
                                    )}
                                </div>
                                <ChevronDown className="hidden h-3.5 w-3.5 shrink-0 text-muted-foreground lg:block" />
                            </button>

                            {userMenuOpen && (
                                <div className="absolute right-0 top-[calc(100%+0.5rem)] z-[60] w-44 rounded-xl border border-border/60 bg-card py-1 shadow-soft">
                                    <button
                                        type="button"
                                        onClick={handleLogout}
                                        className="flex w-full items-center gap-2 px-3 py-2 text-xs text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                    >
                                        <LogOut className="h-3.5 w-3.5" />
                                        Cerrar sesión
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Nav Pills — mobile */}
                <div className="-mx-1 overflow-x-auto pb-0.5 md:hidden">
                    <nav
                        className="flex w-max min-w-full items-center gap-0.5 rounded-full border border-border/50 bg-card/50 px-1 py-1"
                        aria-label="Módulos SUNAT"
                    >
                        {NAV_ITEMS.map((item) => {
                            const isActive = url.startsWith(item.match);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="relative shrink-0 rounded-full px-3.5 py-1.5 text-sm transition-colors"
                                >
                                    {isActive && (
                                        <span className="absolute inset-0 rounded-full bg-accent" />
                                    )}
                                    <span
                                        className={`relative z-10 whitespace-nowrap ${
                                            isActive
                                                ? 'font-medium text-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {item.label}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>
                </div>
            </div>
        </header>
    );
}
