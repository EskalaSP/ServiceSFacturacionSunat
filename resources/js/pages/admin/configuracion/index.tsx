import { Head, useForm } from '@inertiajs/react';
import { Building2, Globe, Infinity as InfinityIcon, Settings2, Sparkles } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import type { BreadcrumbItem } from '@/types';

type Config = {
    emision_ilimitada_global: boolean;
    nuevas_empresas_ilimitadas: boolean;
};

type Stats = {
    empresas_total: number;
    empresas_ilimitadas: number;
    empresas_por_plan: number;
};

type Props = { config: Config; stats: Stats };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Configuración', href: '/admin/configuracion' },
];

const Stat = ({ label, value, icon: Icon }: { label: string; value: number; icon: typeof Building2 }) => (
    <Card className="flex items-center gap-3 p-4">
        <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
            <Icon className="size-5" />
        </div>
        <div>
            <div className="text-2xl font-semibold leading-none">{value}</div>
            <div className="text-muted-foreground text-xs">{label}</div>
        </div>
    </Card>
);

export default function ConfiguracionIndex({ config, stats }: Props) {
    const { data, setData, put, processing } = useForm({
        emision_ilimitada_global: config.emision_ilimitada_global,
        nuevas_empresas_ilimitadas: config.nuevas_empresas_ilimitadas,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/admin/configuracion', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuración de emisión" />

            <form onSubmit={submit} className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                        <Settings2 className="size-5" />
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Configuración de emisión</h1>
                        <p className="text-muted-foreground text-sm">
                            Controla globalmente si las empresas emiten con o sin límites.
                        </p>
                    </div>
                </div>

                {/* Estadísticas rápidas */}
                <div className="grid gap-3 sm:grid-cols-3">
                    <Stat label="Empresas totales" value={stats.empresas_total} icon={Building2} />
                    <Stat label="Con emisión ilimitada" value={stats.empresas_ilimitadas} icon={InfinityIcon} />
                    <Stat label="Controladas por plan" value={stats.empresas_por_plan} icon={Sparkles} />
                </div>

                {/* Switch 1: global */}
                <Card className="p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                                <Globe className="size-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-semibold leading-tight">
                                    Emisión ilimitada global
                                </h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Si lo activas, <strong>todas las empresas</strong> emiten comprobantes sin
                                    ningún límite, sin importar su plan. Tiene la máxima prioridad.
                                </p>
                            </div>
                        </div>
                        <Switch
                            checked={data.emision_ilimitada_global}
                            onCheckedChange={(v) => setData('emision_ilimitada_global', v)}
                        />
                    </div>

                    {data.emision_ilimitada_global && (
                        <div className="mt-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-950/20 dark:text-amber-300">
                            <strong>Modo ilimitado global activo.</strong> Los planes y suscripciones quedan en
                            pausa para el control de emisión mientras esto esté encendido.
                        </div>
                    )}
                </Card>

                {/* Switch 2: default nuevas empresas */}
                <Card className="p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                                <Sparkles className="size-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-semibold leading-tight">
                                    Nuevas empresas ilimitadas por defecto
                                </h3>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Cada empresa que registres nacerá en modo <strong>ilimitado</strong>, sin
                                    necesidad de asignarle un plan. Podrás cambiarlo por empresa al registrarla.
                                </p>
                            </div>
                        </div>
                        <Switch
                            checked={data.nuevas_empresas_ilimitadas}
                            onCheckedChange={(v) => setData('nuevas_empresas_ilimitadas', v)}
                        />
                    </div>
                </Card>

                <div className="flex justify-end gap-3 border-t pt-4">
                    <Button type="submit" disabled={processing}>
                        Guardar configuración
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
