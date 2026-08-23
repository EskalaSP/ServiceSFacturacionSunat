import { Head, router, usePage } from '@inertiajs/react';
import { Check, Copy, KeyRound, Pencil, Power, Trash2, UserPlus, Users, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SunatLayout from '@/layouts/sunat-layout';
import type { SharedData } from '@/types';

type Catalogo = {
    tipos: Record<string, string>;
    acciones: Record<string, string>;
    modulos: Record<string, string>;
    asignables: string[];
    preset: string[];
};

type Cajero = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    abilities: string[];
};

type PageProps = SharedData & {
    cajeros: Cajero[];
    catalogo: Catalogo;
};

function PermissionEditor({
    value,
    onChange,
    catalogo,
}: {
    value: string[];
    onChange: (next: string[]) => void;
    catalogo: Catalogo;
}) {
    const asignables = useMemo(() => new Set(catalogo.asignables), [catalogo.asignables]);
    const has = (a: string) => value.includes(a);
    const toggle = (a: string) => onChange(has(a) ? value.filter((x) => x !== a) : [...value, a]);

    const acciones = Object.entries(catalogo.acciones);
    const modulos = Object.entries(catalogo.modulos).filter(([key]) => asignables.has(key));

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="secondary" onClick={() => onChange([...catalogo.preset])}>
                    Preset recomendado
                </Button>
                <Button type="button" size="sm" variant="secondary" onClick={() => onChange([...catalogo.asignables])}>
                    Todos
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => onChange([])}>
                    Ninguno
                </Button>
            </div>

            {/* Comprobantes: matriz tipo × acción */}
            <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
                            <th className="px-3 py-2 font-medium">Comprobante</th>
                            {acciones.map(([key, label]) => (
                                <th key={key} className="px-3 py-2 text-center font-medium">
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {Object.entries(catalogo.tipos).map(([tipo, label]) => (
                            <tr key={tipo} className="border-b border-border/60 last:border-0">
                                <td className="px-3 py-2 font-medium text-foreground">{label}</td>
                                {acciones.map(([accion]) => {
                                    const ability = `${tipo}.${accion}`;
                                    const permitido = asignables.has(ability);
                                    return (
                                        <td key={accion} className="px-3 py-2 text-center">
                                            {permitido ? (
                                                <Checkbox
                                                    checked={has(ability)}
                                                    onCheckedChange={() => toggle(ability)}
                                                    aria-label={`${label} · ${accion}`}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground/30">—</span>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Módulos transversales */}
            {modulos.length > 0 && (
                <div>
                    <p className="mb-2 text-xs font-medium text-muted-foreground">Otros permisos</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {modulos.map(([key, label]) => (
                            <label
                                key={key}
                                className="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm"
                            >
                                <Checkbox checked={has(key)} onCheckedChange={() => toggle(key)} />
                                <span>{label}</span>
                            </label>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

export default function EquipoIndex() {
    const { props } = usePage<PageProps>();
    const { cajeros, catalogo } = props;
    const nuevoCajero = props.flash?.nuevoCajero ?? null;

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [abilities, setAbilities] = useState<string[]>([...catalogo.preset]);
    const [saving, setSaving] = useState(false);

    const [editId, setEditId] = useState<number | null>(null);
    const [editAbilities, setEditAbilities] = useState<string[]>([]);

    const [copiado, setCopiado] = useState(false);

    const crear = () => {
        setSaving(true);
        router.post(
            '/sunat/equipo',
            { name, email, abilities },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    setEmail('');
                    setAbilities([...catalogo.preset]);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const abrirEdicion = (c: Cajero) => {
        setEditId(c.id);
        setEditAbilities([...c.abilities]);
    };

    const guardarEdicion = (c: Cajero) => {
        router.put(
            `/sunat/equipo/${c.id}`,
            { abilities: editAbilities, is_active: c.is_active },
            { preserveScroll: true, onSuccess: () => setEditId(null) },
        );
    };

    const toggleActivo = (c: Cajero) => {
        router.post(`/sunat/equipo/${c.id}/toggle`, {}, { preserveScroll: true });
    };

    const eliminar = (c: Cajero) => {
        if (!confirm(`¿Quitar a ${c.name} de tu equipo?`)) return;
        router.delete(`/sunat/equipo/${c.id}`, { preserveScroll: true });
    };

    const copiarPassword = () => {
        if (!nuevoCajero) return;
        navigator.clipboard.writeText(nuevoCajero.password).then(() => {
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        });
    };

    return (
        <SunatLayout>
            <Head title="Mi equipo" />

            <div className="mx-auto max-w-4xl space-y-6">
                <header className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-primary">
                        <Users className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Mi equipo</h1>
                        <p className="text-sm text-muted-foreground">
                            Crea cajeros para tu empresa y decide qué puede hacer cada uno.
                        </p>
                    </div>
                </header>

                {/* Contraseña temporal del cajero recién creado */}
                {nuevoCajero && (
                    <div className="rounded-xl border border-warning/40 bg-warning/5 p-4">
                        <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-foreground">
                            <KeyRound className="size-4" /> Cajero creado — guarda su contraseña
                        </div>
                        <p className="mb-3 text-xs text-muted-foreground">
                            Esta contraseña <strong>no se vuelve a mostrar</strong>. Entrégasela al cajero;
                            podrá cambiarla al ingresar.
                        </p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                <div className="text-[10px] uppercase text-muted-foreground">Correo</div>
                                <div className="font-mono">{nuevoCajero.email}</div>
                            </div>
                            <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                <div>
                                    <div className="text-[10px] uppercase text-muted-foreground">Contraseña</div>
                                    <div className="font-mono">{nuevoCajero.password}</div>
                                </div>
                                <Button type="button" size="sm" variant="secondary" onClick={copiarPassword}>
                                    {copiado ? <Check className="size-4" /> : <Copy className="size-4" />}
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Nuevo cajero */}
                <section className="rounded-xl border border-border bg-card p-5">
                    <h2 className="mb-4 flex items-center gap-2 font-semibold text-foreground">
                        <UserPlus className="size-4" /> Nuevo cajero
                    </h2>
                    <div className="mb-4 grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Ana Torres" />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="cajero@empresa.com"
                            />
                        </div>
                    </div>

                    <p className="mb-2 text-sm font-medium text-foreground">Permisos</p>
                    <PermissionEditor value={abilities} onChange={setAbilities} catalogo={catalogo} />

                    <div className="mt-5">
                        <Button type="button" onClick={crear} disabled={saving || !name || !email}>
                            <UserPlus className="size-4" /> Crear cajero
                        </Button>
                    </div>
                </section>

                {/* Lista de cajeros */}
                <section className="space-y-3">
                    <h2 className="font-semibold text-foreground">Cajeros ({cajeros.length})</h2>

                    {cajeros.length === 0 && (
                        <p className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            Aún no tienes cajeros. Crea uno arriba.
                        </p>
                    )}

                    {cajeros.map((c) => (
                        <div key={c.id} className="rounded-xl border border-border bg-card">
                            <div className="flex flex-wrap items-center gap-3 p-4">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate font-medium text-foreground">{c.name}</span>
                                        {c.is_active ? (
                                            <Badge variant="secondary">Activo</Badge>
                                        ) : (
                                            <Badge variant="outline">Inactivo</Badge>
                                        )}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {c.email} · {c.abilities.length} permiso(s)
                                    </div>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button type="button" size="sm" variant="ghost" onClick={() => abrirEdicion(c)} title="Editar permisos">
                                        <Pencil className="size-4" />
                                    </Button>
                                    <Button type="button" size="sm" variant="ghost" onClick={() => toggleActivo(c)} title={c.is_active ? 'Desactivar' : 'Activar'}>
                                        <Power className="size-4" />
                                    </Button>
                                    <Button type="button" size="sm" variant="ghost" onClick={() => eliminar(c)} title="Quitar">
                                        <Trash2 className="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </div>

                            {editId === c.id && (
                                <div className="border-t border-border p-4">
                                    <PermissionEditor value={editAbilities} onChange={setEditAbilities} catalogo={catalogo} />
                                    <div className="mt-4 flex gap-2">
                                        <Button type="button" size="sm" onClick={() => guardarEdicion(c)}>
                                            <Check className="size-4" /> Guardar
                                        </Button>
                                        <Button type="button" size="sm" variant="ghost" onClick={() => setEditId(null)}>
                                            <X className="size-4" /> Cancelar
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                </section>
            </div>
        </SunatLayout>
    );
}
