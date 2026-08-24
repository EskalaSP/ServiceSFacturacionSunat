import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessages } from '@/components/sunat/flash-messages';
import { SunatSidebar } from '@/components/sunat/sunat-sidebar';

interface SunatLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export default function SunatLayout({ children }: SunatLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <SunatSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader />
                <FlashMessages />
                <main className="mx-auto w-full max-w-[1400px] px-4 pt-4 pb-[max(2rem,env(safe-area-inset-bottom))] sm:px-6">
                    {children}
                </main>
            </AppContent>
        </AppShell>
    );
}
