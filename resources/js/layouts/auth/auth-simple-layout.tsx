import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center bg-background p-6">
            {/* Marca superior */}
            <Link
                href={home()}
                className="mb-8 flex flex-col items-center gap-2 text-center"
            >
                <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl bg-card shadow-sm ring-1 ring-border">
                    <img
                        src="/logo.png"
                        alt="Jorge Chavez API SUNAT"
                        className="h-14 w-14 object-contain"
                    />
                </div>
                <div className="mt-1">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground">
                        Jorge Chavez
                    </h2>
                    <p className="text-xs font-medium uppercase tracking-widest text-muted-foreground">
                        API SUNAT
                    </p>
                </div>
            </Link>

            {/* Card del formulario */}
            <div className="w-full max-w-sm rounded-xl border border-border bg-card p-8 shadow-sm">
                <div className="mb-6 space-y-1.5 text-center">
                    <h1 className="text-xl font-semibold text-card-foreground">
                        {title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {children}
            </div>

            {/* Footer discreto */}
            <p className="mt-8 text-center text-xs text-muted-foreground">
                Facturación electrónica Perú · SUNAT
            </p>
        </div>
    );
}
