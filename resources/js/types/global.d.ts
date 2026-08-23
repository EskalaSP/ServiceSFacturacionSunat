import type { Auth } from '@/types/auth';
import type { EmpresaShared, FlashShared, TenantShared } from '@/types/shared';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            tenant: TenantShared;
            empresa: EmpresaShared;
            flash?: FlashShared;
            [key: string]: unknown;
        };
    }
}
