import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Copy, Check, Eye, EyeOff, Key } from 'lucide-react';
import SunatLayout from '@/layouts/sunat-layout';

type Props = {
    api_key:     string;
    api_secret:  string;
    ruc:         string;
    razon_social: string;
    environment: string;
};

function CopyField({ label, value, secret = false }: { label: string; value: string; secret?: boolean }) {
    const [copied, setCopied]     = useState(false);
    const [visible, setVisible]   = useState(!secret);

    function copy() {
        navigator.clipboard.writeText(value).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-muted-foreground">{label}</label>
            <div className="flex gap-2">
                <div className="flex flex-1 items-center gap-2 rounded-xl border border-border bg-muted/40 px-3 py-2.5">
                    <code className="flex-1 truncate text-xs font-mono text-foreground">
                        {visible ? value : '•'.repeat(Math.min(value.length, 32))}
                    </code>
                    {secret && (
                        <button type="button" onClick={() => setVisible((v) => !v)}
                            className="shrink-0 text-muted-foreground hover:text-foreground transition-colors">
                            {visible ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                        </button>
                    )}
                </div>
                <button type="button" onClick={copy}
                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition-colors ${
                        copied
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-600 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                            : 'border-border bg-secondary text-muted-foreground hover:bg-muted hover:text-foreground'
                    }`}
                    title="Copiar"
                >
                    {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                </button>
            </div>
        </div>
    );
}

export default function MiApiKey({ api_key, api_secret, ruc, razon_social, environment }: Props) {
    return (
        <SunatLayout>
            <Head title="Mi API Key" />
            <div className="mx-auto max-w-xl">

                <div className="mb-5 flex items-center gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">Mis credenciales API</h1>
                    {environment !== 'produccion' && (
                        <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                            Beta
                        </span>
                    )}
                </div>

                <div className="rounded-2xl border border-border bg-card shadow-soft">

                    <div className="flex items-center gap-3 border-b border-border/60 px-5 py-4">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-accent">
                            <Key className="h-4 w-4 text-primary" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-foreground">{razon_social || 'Tu empresa'}</div>
                            <div className="text-xs text-muted-foreground">RUC {ruc}</div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-4 p-5">

                        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                            Guarda estas credenciales en un lugar seguro. El <strong>API Secret</strong> no se puede recuperar si lo pierdes — solo regenerar.
                        </div>

                        <CopyField label="API Key" value={api_key} />
                        <CopyField label="API Secret" value={api_secret} secret />

                    </div>
                </div>

                <p className="mt-4 text-center text-xs text-muted-foreground">
                    Usa estas credenciales para conectar Zaresk u otras aplicaciones con tu módulo SUNAT.
                </p>
            </div>
        </SunatLayout>
    );
}
