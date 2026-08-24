import { Head, router, usePage } from '@inertiajs/react';
import { Check, Copy, Eye, EyeOff, KeyRound, Loader2, Pencil, Power, RefreshCw, Search, Trash2, UserPlus, Users, X } from 'lucide-react';
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

/** Genera una contraseña segura de 12 caracteres (sin caracteres ambiguos). */
function generarPassword(): string {
    const mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const minus = 'abcdefghijkmnpqrstuvwxyz';
    const nums = '23456789';
    const simb = '!@#$%*?';
    const todos = mayus + minus + nums + simb;
    const rnd = (set: string) => set[Math.floor(Math.random() * set.length)];
    // Garantiza al menos uno de cada grupo y completa hasta 12.
    const base = [rnd(mayus), rnd(minus), rnd(nums), rnd(simb)];
    while (base.length < 12) base.push(rnd(todos));
    // Mezcla (Fisher–Yates).
    for (let i = base.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [base[i], base[j]] = [base[j], base[i]];
    }
    return base.join('');
}

/** Sugiere un correo a partir del nombre y el dominio del dueño (la empresa). */
function sugerirCorreo(nombre: string, correoDueno: string): string {
    const slug = nombre
        .normalize('NFD')          // separa los acentos como marcas combinantes
        .toLowerCase()
        .replace(/[^a-z\s]/g, '')  // elimina esas marcas y cualquier símbolo
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .join('.');
    if (!slug) return '';
    const dominio = correoDueno.split('@')[1] || 'empresa.com';
    return `${slug}@${dominio}`;
}

export default function EquipoIndex() {
    const { props } = usePage<PageProps>();
    const { cajeros, catalogo } = props;
    const nuevoCajero = props.flash?.nuevoCajero ?? null;
    const correoDueno = props.auth?.user?.email ?? '';

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPass, setShowPass] = useState(false);
    const [documento, setDocumento] = useState('');
    const [buscando, setBuscando] = useState(false);
    const [lookupMsg, setLookupMsg] = useState<{ ok: boolean; texto: string } | null>(null);
    const [abilities, setAbilities] = useState<string[]>([...catalogo.preset]);
    const [saving, setSaving] = useState(false);

    const [editId, setEditId] = useState<number | null>(null);
    const [editAbilities, setEditAbilities] = useState<string[]>([]);

    const [copiado, setCopiado] = useState(false);

    async function buscarDocumento() {
        const n = documento.trim();
        if (n.length !== 8 && n.length !== 11) {
            setLookupMsg({ ok: false, texto: 'Ingresa 8 dígitos (DNI) u 11 (RUC).' });
            return;
        }
        setBuscando(true);
        setLookupMsg(null);
        try {
            const res = await fetch(`/sunat/buscar-ruc?numero=${encodeURIComponent(n)}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!res.ok) {
                setLookupMsg({ ok: false, texto: data.error ?? 'No se encontró el documento.' });
                return;
            }
            if (data.razon_social) {
                setName(data.razon_social);
                // Sugiere un correo solo si el dueño aún no escribió uno.
                setEmail((actual) => actual.trim() || sugerirCorreo(data.razon_social, correoDueno));
                setLookupMsg({ ok: true, texto: `Encontrado: ${data.razon_social}` });
            } else {
                setLookupMsg({ ok: false, texto: 'Sin resultados para ese documento.' });
            }
        } catch {
            setLookupMsg({ ok: false, texto: 'No se pudo conectar con el servicio de consulta.' });
        } finally {
            setBuscando(false);
        }
    }

    const crear = () => {
        setSaving(true);
        router.post(
            '/sunat/equipo',
            { name, email, password: password || undefined, abilities },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    setEmail('');
                    setPassword('');
                    setDocumento('');
                    setLookupMsg(null);
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
                            <KeyRound className="size-4" /> Cajero creado - guarda su contraseña
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
                    {/* Buscar por DNI / RUC con el token de consulta */}
                    <div className="mb-4 grid gap-2">
                        <Label htmlFor="documento">Buscar por DNI / RUC (opcional)</Label>
                        <div className="flex gap-2">
                            <Input
                                id="documento"
                                inputMode="numeric"
                                value={documento}
                                onChange={(e) => setDocumento(e.target.value.replace(/\D/g, '').slice(0, 11))}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); buscarDocumento(); } }}
                                placeholder="DNI (8 dígitos) o RUC (11 dígitos)"
                                className="max-w-xs"
                            />
                            <Button type="button" variant="secondary" onClick={buscarDocumento} disabled={buscando || documento.trim().length < 8}>
                                {buscando ? <Loader2 className="size-4 animate-spin" /> : <Search className="size-4" />}
                                Buscar
                            </Button>
                        </div>
                        {lookupMsg && (
                            <p className={`text-xs ${lookupMsg.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}`}>
                                {lookupMsg.texto}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Autocompleta el nombre usando tu token de consulta (Configuración → Consulta RUC/DNI).
                        </p>
                    </div>

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
                        <div className="grid gap-2 sm:col-span-2 sm:max-w-md">
                            <Label htmlFor="password">Contraseña</Label>
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Input
                                        id="password"
                                        type={showPass ? 'text' : 'password'}
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        placeholder="Déjala vacía para generar una"
                                        autoComplete="new-password"
                                        className="pr-9"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPass((v) => !v)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        {showPass ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => { setPassword(generarPassword()); setShowPass(true); }}
                                >
                                    <RefreshCw className="size-4" /> Generar
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">Mínimo 8 caracteres. Si la dejas vacía, se genera una y se te muestra al crear.</p>
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
