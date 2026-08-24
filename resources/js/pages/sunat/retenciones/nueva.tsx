import { Head } from '@inertiajs/react';
import { Percent } from 'lucide-react';
import RetPercForm from '@/components/sunat/ret-perc-form';
import SunatLayout from '@/layouts/sunat-layout';

export default function NuevaRetencion() {
    return (
        <SunatLayout>
            <Head title="Nueva retención" />
            <div className="mx-auto max-w-4xl">
                <header className="mb-5 flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <Percent className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Comprobante de retención</h1>
                        <p className="text-sm text-muted-foreground">Agente de retención (Régimen SUNAT).</p>
                    </div>
                </header>
            </div>
            <RetPercForm
                config={{
                    titulo: 'Retención',
                    docTipo: '20',
                    entidadKey: 'proveedor',
                    entidadLabel: 'Proveedor (a quien se retiene)',
                    pagosKey: 'pagos',
                    fechaKey: 'fecha_retencion',
                    seriePlaceholder: 'R001',
                    postUrl: '/sunat/retenciones',
                    regimenes: [
                        { code: '01', label: 'Tasa 3%', tasa: 3 },
                        { code: '02', label: 'Tasa 6%', tasa: 6 },
                    ],
                }}
            />
        </SunatLayout>
    );
}
