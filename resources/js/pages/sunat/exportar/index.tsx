import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { MultiSelect } from '@/components/multi-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import { armarQuery, descargarArchivo } from '@/lib/descarga';
import { todayLimaDate } from '@/lib/lima-date';

const TIPOS = [
    { value: 'factura', label: 'Facturas' },
    { value: 'boleta', label: 'Boletas' },
    { value: 'nota-credito', label: 'Notas de crédito' },
    { value: 'nota-debito', label: 'Notas de débito' },
];
const ESTADOS = [
    { value: 'aceptado', label: 'Aceptado' },
    { value: 'aceptado_obs', label: 'Aceptado con observaciones' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'enviado', label: 'Enviado' },
    { value: 'rechazado', label: 'Rechazado' },
    { value: 'anulado', label: 'Anulado' },
    { value: 'anulacion_en_proceso', label: 'Anulación en proceso' },
];
const AMBIENTES = [
    { value: 'produccion', label: 'Producción' },
    { value: 'prueba', label: 'Prueba' },
];

const hoy = () => todayLimaDate();

export default function Exportar() {
    const [desde, setDesde] = useState(hoy());
    const [hasta, setHasta] = useState(hoy());
    const [tipo, setTipo] = useState('xml');
    const [tipos, setTipos] = useState<string[]>(['factura', 'boleta']);
    const [estados, setEstados] = useState<string[]>([
        'aceptado',
        'aceptado_obs',
    ]);
    const [ambientes, setAmbientes] = useState<string[]>(['produccion']);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState('');

    const descargar = async () => {
        setCargando(true);
        setError('');
        const query = armarQuery({
            fecha_desde: desde,
            fecha_hasta: hasta,
            tipo,
            tipos,
            estados,
            ambientes,
        });
        const r = await descargarArchivo(
            `/sunat/exportar/descargar?${query}`,
            `comprobantes_${desde}_${hasta}.zip`,
        );
        if (!r.ok) setError(r.error);
        setCargando(false);
    };

    const incompleto =
        tipos.length === 0 || estados.length === 0 || ambientes.length === 0;

    return (
        <SunatLayout>
            <Head title="Exportar comprobantes" />
            <div className="mx-auto max-w-2xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <Download className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">
                            Exportar comprobantes
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Descarga un ZIP con los XML/PDF de un rango de
                            fechas.
                        </p>
                    </div>
                </header>

                <section className="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label>Desde</Label>
                            <Input
                                type="date"
                                value={desde}
                                onChange={(e) => setDesde(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Hasta</Label>
                            <Input
                                type="date"
                                value={hasta}
                                onChange={(e) => setHasta(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Formato</Label>
                            <select
                                value={tipo}
                                onChange={(e) => setTipo(e.target.value)}
                                className="h-10 rounded-xl border border-input bg-card px-3 text-sm dark:border-border dark:bg-background"
                            >
                                <option value="xml">XML</option>
                                <option value="pdf">PDF</option>
                                <option value="ambos">Ambos</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label>Tipo de documento</Label>
                            <MultiSelect
                                value={tipos}
                                onChange={setTipos}
                                options={TIPOS}
                                placeholder="Ninguno"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Estado</Label>
                            <MultiSelect
                                value={estados}
                                onChange={setEstados}
                                options={ESTADOS}
                                placeholder="Ninguno"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Ambiente</Label>
                            <MultiSelect
                                value={ambientes}
                                onChange={setAmbientes}
                                options={AMBIENTES}
                                placeholder="Ninguno"
                            />
                        </div>
                    </div>

                    {error && (
                        <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                            {error}
                        </div>
                    )}

                    <Button
                        type="button"
                        onClick={descargar}
                        disabled={incompleto || cargando}
                    >
                        <Download className="size-4" />{' '}
                        {cargando ? 'Generando…' : 'Descargar ZIP'}
                    </Button>
                </section>
            </div>
        </SunatLayout>
    );
}
