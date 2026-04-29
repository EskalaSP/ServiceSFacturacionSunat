import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FileX2,
    FilePlus,
    FolderGit2,
    History,
    LayoutGrid,
    Receipt,
    Settings2,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const sunatNavItems: NavItem[] = [
    { title: 'Panel SUNAT',    href: '/sunat',                icon: Building2  },
    { title: 'Nueva Factura',  href: '/sunat/facturas/nueva', icon: FilePlus   },
    { title: 'Nueva Boleta',   href: '/sunat/facturas/nueva?tipo=boleta', icon: Receipt    },
    { title: 'Historial',      href: '/sunat/historial',      icon: History    },
    { title: 'Nota de Crédito', href: '/sunat/nota-credito/nueva', icon: FileX2     },
    { title: 'Configuración',  href: '/sunat/configuracion',  icon: Settings2  },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>SUNAT</SidebarGroupLabel>
                    <SidebarMenu>
                        {sunatNavItems.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={
                                        item.href === '/sunat'
                                            ? isCurrentOrParentUrl('/sunat')
                                            : isCurrentUrl(item.href)
                                    }
                                    tooltip={{ children: item.title }}
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
