import { Head } from '@inertiajs/react';
import { Percent } from 'lucide-react';
import RetPercForm from '@/components/sunat/ret-perc-form';
import SunatLayout from '@/layouts/sunat-layout';

export default function NuevaPercepcion() {
    return (
        <SunatLayout>
            <Head title="Nueva percepción" />
            <div className="mx-auto max-w-3xl">
                <header className="mb-5 flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <Percent className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Comprobante de percepción</h1>
                        <p className="text-sm text-muted-foreground">Agente de percepción (Régimen SUNAT).</p>
                    </div>
                </header>
            </div>
            <RetPercForm
                config={{
                    titulo: 'Percepción',
                    entidadKey: 'cliente',
                    entidadLabel: 'Cliente (a quien se percibe)',
                    pagosKey: 'cobros',
                    fechaKey: 'fecha_percepcion',
                    seriePlaceholder: 'P001',
                    postUrl: '/sunat/percepciones',
                    regimenes: [
                        { code: '01', label: 'Tasa 2%', tasa: 2 },
                        { code: '02', label: 'Tasa 1%', tasa: 1 },
                        { code: '03', label: 'Tasa 0.5%', tasa: 0.5 },
                    ],
                }}
            />
        </SunatLayout>
    );
}
