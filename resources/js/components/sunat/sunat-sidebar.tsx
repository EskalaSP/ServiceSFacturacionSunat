import { Link, router, usePage } from '@inertiajs/react';
import {
    Ban,
    BarChart3,
    Building2,
    CalendarDays,
    ChevronRight,
    ClipboardList,
    Database,
    Download,
    FileMinus,
    FilePlus,
    FileSpreadsheet,
    FileStack,
    FileText,
    Hash,
    History,
    KeyRound,
    LayoutDashboard,
    Percent,
    Receipt,
    ReceiptText,
    Search,
    Settings2,
    Store,
    Truck,
    Undo2,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { SharedData } from '@/types';
import type { LucideIcon } from 'lucide-react';

type Item = { title: string; href: string; icon: LucideIcon; can?: string; canAny?: string[] };
type Grupo = { label: string; icon: LucideIcon; items: Item[] };

const GRUPOS: Grupo[] = [
    {
        label: 'Emitir',
        icon: FileText,
        items: [
            { title: 'Factura', href: '/sunat/facturas', icon: FileText, can: 'factura.emitir' },
            { title: 'Boleta', href: '/sunat/boletas', icon: Receipt, can: 'boleta.emitir' },
            { title: 'Nota de venta', href: '/sunat/nota-venta', icon: ReceiptText, can: 'nota_venta.emitir' },
            { title: 'Cotizaciones', href: '/sunat/cotizaciones', icon: FileSpreadsheet, can: 'cotizacion.emitir' },
            { title: 'Nota de crédito', href: '/sunat/nota-credito', icon: FileMinus, can: 'nota_credito.emitir' },
            { title: 'Nota de débito', href: '/sunat/nota-debito', icon: FilePlus, can: 'nota_debito.emitir' },
            { title: 'Guías de remisión', href: '/sunat/guias', icon: Truck, canAny: ['guia_remitente.emitir', 'guia_transportista.emitir'] },
        ],
    },
    {
        label: 'Trámites',
        icon: ClipboardList,
        items: [
            { title: 'Anulación', href: '/sunat/anulaciones/nueva', icon: Ban, can: 'anulacion.emitir' },
            { title: 'Resúmenes diarios', href: '/sunat/resumenes', icon: CalendarDays, can: 'resumen.emitir' },
            { title: 'Retención', href: '/sunat/retenciones/nueva', icon: Percent, can: 'retencion.emitir' },
            { title: 'Percepción', href: '/sunat/percepciones/nueva', icon: Percent, can: 'percepcion.emitir' },
            { title: 'Reversión', href: '/sunat/reversiones/nueva', icon: Undo2, can: 'reversion.emitir' },
        ],
    },
    {
        label: 'Consultas',
        icon: FileStack,
        items: [
            { title: 'Historial', href: '/sunat/historial', icon: History },
            { title: 'Reportes', href: '/sunat/reportes', icon: BarChart3, can: 'reporte.ver' },
            { title: 'Consultar SUNAT', href: '/sunat/consulta-cpe', icon: Search, can: 'consulta.cpe' },
            { title: 'Exportar (ZIP)', href: '/sunat/exportar', icon: Download, can: 'exportar' },
        ],
    },
    {
        label: 'Empresa',
        icon: Building2,
        items: [
            { title: 'Clientes', href: '/sunat/clientes', icon: Users, can: 'cliente.gestionar' },
            { title: 'Series', href: '/sunat/series', icon: Hash, can: 'serie.gestionar' },
            { title: 'Sucursales', href: '/sunat/sucursales', icon: Store, can: 'sucursal.gestionar' },
            { title: 'Mi equipo', href: '/sunat/equipo', icon: UsersRound, can: 'equipo.gestionar' },
            { title: 'SIRE (RCE)', href: '/sunat/sire', icon: Database, can: 'sire.gestionar' },
            { title: 'Mi API Key', href: '/sunat/mi-api-key', icon: KeyRound, can: 'apikey.ver' },
            { title: 'Configuración', href: '/sunat/configuracion', icon: Settings2, can: 'config.editar' },
        ],
    },
];

export function SunatSidebar() {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { props } = usePage<SharedData>();
    const empresa = props.empresa;
    const tenant = props.tenant;

    const has = (a: string) => empresa?.can?.includes(a) ?? false;
    const visible = (item: Item) => (!item.can || has(item.can)) && (!item.canAny || item.canAny.some(has));

    const disponibles = empresa?.disponibles ?? [];
    const puedeCambiar = disponibles.length > 1;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="h-auto py-2">
                            <Link href="/sunat" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {tenant && puedeCambiar && (
                    <div className="px-2 pb-1 group-data-[collapsible=icon]:hidden">
                        <select
                            value={tenant.id}
                            onChange={(e) => router.put('/sunat/empresa-activa', { tenant_id: Number(e.target.value) }, { preserveScroll: true })}
                            className="w-full truncate rounded-lg border border-sidebar-border bg-sidebar-accent px-2.5 py-1.5 text-xs text-sidebar-foreground outline-none"
                            title="Cambiar de empresa"
                        >
                            {disponibles.map((op) => (
                                <option key={op.id} value={op.id}>{op.razon_social} · {op.ruc}</option>
                            ))}
                        </select>
                    </div>
                )}
                {tenant && !puedeCambiar && (
                    <div className="px-2 pb-1 text-[11px] leading-tight text-sidebar-foreground/60 group-data-[collapsible=icon]:hidden">
                        {tenant.ruc} · {tenant.environment === 'beta' ? 'Beta' : 'Producción'}
                    </div>
                )}
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild isActive={isCurrentUrl('/sunat')} tooltip={{ children: 'Panel' }}>
                                <Link href="/sunat" prefetch>
                                    <LayoutDashboard />
                                    <span>Panel</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        {GRUPOS.map((grupo) => {
                            const items = grupo.items.filter(visible);
                            if (items.length === 0) return null;

                            const grupoActivo = items.some((i) => isCurrentOrParentUrl(i.href));

                            return (
                                <Collapsible key={grupo.label} asChild defaultOpen={grupoActivo} className="group/collapsible">
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton tooltip={{ children: grupo.label }}>
                                                <grupo.icon />
                                                <span>{grupo.label}</span>
                                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {items.map((item) => (
                                                    <SidebarMenuSubItem key={item.href}>
                                                        <SidebarMenuSubButton asChild isActive={isCurrentOrParentUrl(item.href)}>
                                                            <Link href={item.href} prefetch>
                                                                <item.icon />
                                                                <span>{item.title}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>
        </Sidebar>
    );
}
