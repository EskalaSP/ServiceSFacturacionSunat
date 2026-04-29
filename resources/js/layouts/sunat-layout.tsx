import { SunatTopNav } from '@/components/sunat/sunat-top-nav';
import { FlashMessages } from '@/components/sunat/flash-messages';

interface SunatLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export default function SunatLayout({ children }: SunatLayoutProps) {
    return (
        <div className="min-h-screen dashboard-surface">
            <SunatTopNav />
            <FlashMessages />
            <main className="mx-auto max-w-[1680px] px-4 pt-6 pb-[max(2rem,env(safe-area-inset-bottom))] sm:px-6">
                {children}
            </main>
        </div>
    );
}
