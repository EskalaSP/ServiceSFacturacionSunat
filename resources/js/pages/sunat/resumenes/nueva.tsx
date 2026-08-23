import { Head, router } from '@inertiajs/react';
import { CalendarDays, Send } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import SunatLayout from '@/layouts/sunat-layout';

type FechaPendiente = {
    fecha: string;
    cantidad: number;
    total: number;
};

type Props = {
    fechas: FechaPendiente[];
};

function fmt(n: number) {
    return new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

export default function NuevoResumen({ fechas }: Props) {
    const [enviando, setEnviando] = useState<string | null>(null);

    const enviar = (fecha: string, cantidad: number) => {
        if (!confirm(`¿Enviar el resumen diario de ${cantidad} boleta(s) del ${fecha} a SUNAT?`)) return;
        setEnviando(fecha);
        router.post(
            '/sunat/resumenes',
            { fecha_resumen: fecha },
            { onFinish: () => setEnviando(null) },
        );
    };

    return (
        <SunatLayout>
            <Head title="Resumen diario" />

            <div className="mx-auto max-w-2xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <CalendarDays className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Resumen diario de boletas</h1>
                        <p className="text-sm text-muted-foreground">
                            Envía a SUNAT las boletas pendientes agrupadas por día de emisión.
                        </p>
                    </div>
                </header>

                {fechas.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        No tienes boletas pendientes de resumen. Todo al día. 🎉
                    </p>
                ) : (
                    <div className="space-y-3">
                        {fechas.map((f) => (
                            <div
                                key={f.fecha}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4"
                            >
                                <div>
                                    <div className="font-medium text-foreground">{f.fecha}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {f.cantidad} boleta(s) · S/ {fmt(f.total)}
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    onClick={() => enviar(f.fecha, f.cantidad)}
                                    disabled={enviando !== null}
                                >
                                    <Send className="size-4" />
                                    {enviando === f.fecha ? 'Enviando…' : 'Enviar resumen'}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </SunatLayout>
    );
}
