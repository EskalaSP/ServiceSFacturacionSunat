import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

/**
 * Menú de usuario para la esquina superior derecha del header.
 * Muestra avatar + nombre y despliega el menú (perfil, ajustes, cerrar sesión).
 */
export function HeaderUserMenu() {
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const getInitials = useInitials();
    const user = auth.user;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 outline-none focus-visible:outline-none"
                >
                    <Avatar className="size-8 overflow-hidden rounded-full">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="bg-primary/15 text-primary rounded-full text-xs font-semibold">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden text-sm font-medium sm:inline-block max-w-[140px] truncate">
                        {user.name}
                    </span>
                    <ChevronDown className="text-muted-foreground size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56 rounded-lg">
                <UserMenuContent user={user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
