import { useCallback, useRef, useState } from 'react';
import { Loader2, Plus, Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ClienteData = {
    tipo_doc: string;
    num_doc: string;
    razon_social: string;
    direccion?: string;
    email?: string;
};

type ResultadoBusqueda = {
    id: number;
    tipo_documento: string;
    numero_documento: string;
    razon_social: string;
    direccion?: string | null;
    email?: string | null;
};

const TIPO_DOC_OPTS = [
    { value: '6', label: 'RUC' },
    { value: '1', label: 'DNI' },
    { value: '4', label: 'Carné de Extranjería' },
    { value: '7', label: 'Pasaporte' },
    { value: '0', label: 'Otros' },
];

const tipoDocLabel = (t: string) =>
    ({ '6': 'RUC', '1': 'DNI', '4': 'C. Extranjería', '7': 'Pasaporte', '0': 'Otros' })[t] ?? 'Doc.';

type Props = {
    value: ClienteData | null;
    onChange: (c: ClienteData | null) => void;
    label?: string;
    subtitulo?: string;
    error?: string;
    showDireccion?: boolean;
    showEmail?: boolean;
    /** Endpoint de búsqueda local (autocompletar). Default: /sunat/buscar-cliente */
    searchEndpoint?: string;
    /** Permite cambiar el cliente seleccionado. Default: true */
    permitirCambiar?: boolean;
};

/**
 * Selección de cliente/tercero unificada para todos los documentos.
 * Búsqueda local en tiempo real + consulta RUC/DNI por token (api.json.pe)
 * + alta manual + resumen editable. Misma lógica y UX que Facturas/Boletas.
 */
export function ClienteSelector({
    value,
    onChange,
    label = 'Datos del adquirente / usuario',
    subtitulo,
    error,
    showDireccion = true,
    showEmail = true,
    searchEndpoint = '/sunat/buscar-cliente',
    permitirCambiar = true,
}: Props) {
    const [search, setSearch]           = useState('');
    const [results, setResults]         = useState<ResultadoBusqueda[]>([]);
    const [searching, setSearching]     = useState(false);
    const searchRef                     = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    const [manualOpen, setManualOpen]   = useState(false);
    const [draft, setDraft]             = useState<ClienteData>({ tipo_doc: '6', num_doc: '', razon_social: '', direccion: '', email: '' });
    const [draftLoading, setDraftLoading] = useState(false);
    const [draftError, setDraftError]   = useState('');

    const seleccionado = Boolean(value?.razon_social || value?.num_doc);

    const buscar = useCallback((q: string) => {
        clearTimeout(searchRef.current);
        setSearch(q);
        if (q.length < 2) { setResults([]); return; }
        searchRef.current = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await fetch(`${searchEndpoint}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                setResults(await res.json());
            } finally { setSearching(false); }
        }, 300);
    }, [searchEndpoint]);

    function elegir(c: ResultadoBusqueda) {
        onChange({
            tipo_doc: c.tipo_documento,
            num_doc: c.numero_documento,
            razon_social: c.razon_social,
            direccion: c.direccion ?? '',
            email: c.email ?? '',
        });
        setSearch('');
        setResults([]);
    }

    function abrirManual(editar: boolean) {
        setDraft(editar && value
            ? { tipo_doc: value.tipo_doc, num_doc: value.num_doc, razon_social: value.razon_social, direccion: value.direccion ?? '', email: value.email ?? '' }
            : { tipo_doc: '6', num_doc: '', razon_social: '', direccion: '', email: '' });
        setDraftError('');
        setManualOpen(true);
    }

    function confirmarManual() {
        if (!draft.num_doc.trim() || !draft.razon_social.trim()) {
            setDraftError('Ingresa al menos el documento y la razón social / nombre.');
            return;
        }
        onChange({
            tipo_doc: draft.tipo_doc,
            num_doc: draft.num_doc.trim(),
            razon_social: draft.razon_social.trim(),
            direccion: (draft.direccion ?? '').trim(),
            email: (draft.email ?? '').trim(),
        });
        setManualOpen(false);
    }

    async function lookupDraft() {
        const n = draft.num_doc.trim();
        if (n.length !== 8 && n.length !== 11) {
            setDraftError('Ingresa 8 dígitos (DNI) u 11 dígitos (RUC).');
            return;
        }
        setDraftLoading(true);
        setDraftError('');
        try {
            const res = await fetch(`/sunat/buscar-ruc?numero=${encodeURIComponent(n)}`, { headers: { Accept: 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setDraftError(data.error ?? (res.status === 404 ? 'No encontrado.' : 'Error al consultar.'));
                return;
            }
            setDraft((d) => ({
                ...d,
                tipo_doc: data.tipo_documento ?? (n.length === 11 ? '6' : '1'),
                razon_social: data.razon_social ?? d.razon_social,
                direccion: data.direccion ?? d.direccion,
                email: data.email ?? d.email,
            }));
        } catch {
            setDraftError('No se pudo conectar al servicio de consulta.');
        } finally {
            setDraftLoading(false);
        }
    }

    return (
        <section className="rounded-2xl border border-border bg-card shadow-soft">
            <div className="border-b border-border/60 px-5 py-3.5">
                <span className="text-sm font-semibold text-foreground">{label}</span>
                {subtitulo && <p className="mt-0.5 text-xs text-muted-foreground">{subtitulo}</p>}
            </div>

            <div className="p-5">
                {seleccionado ? (
                    <div className="flex items-start justify-between gap-4 rounded-xl border border-border bg-secondary/40 px-4 py-3.5">
                        <div className="min-w-0 space-y-0.5">
                            <p className="truncate text-sm font-semibold text-foreground">{value?.razon_social || '(Sin nombre)'}</p>
                            <p className="text-xs text-muted-foreground">{tipoDocLabel(value?.tipo_doc ?? '')} {value?.num_doc || '—'}</p>
                            {showDireccion && value?.direccion && <p className="truncate text-xs text-muted-foreground">{value.direccion}</p>}
                            {showEmail && value?.email && <p className="truncate text-xs text-muted-foreground">{value.email}</p>}
                        </div>
                        {permitirCambiar && (
                            <div className="flex shrink-0 items-center gap-1.5">
                                <button type="button" onClick={() => abrirManual(true)} className="rounded-lg border border-border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary">Editar</button>
                                <button type="button" onClick={() => onChange(null)} className="rounded-lg border border-border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary">Cambiar</button>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="h-10 rounded-xl pl-9"
                                placeholder="Buscar por RUC, DNI o razón social..."
                                value={search}
                                onChange={(e) => buscar(e.target.value)}
                            />
                            {searching && <Loader2 className="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />}
                            {results.length > 0 && (
                                <div className="absolute z-50 mt-1 w-full rounded-xl border border-border bg-popover shadow-soft">
                                    {results.map((c) => (
                                        <button key={c.id} type="button"
                                            className="flex w-full flex-col px-4 py-2.5 text-left first:rounded-t-xl last:rounded-b-xl hover:bg-secondary"
                                            onClick={() => elegir(c)}>
                                            <span className="text-sm font-medium">{c.razon_social}</span>
                                            <span className="text-xs text-muted-foreground">{c.tipo_documento === '6' ? 'RUC' : 'DNI'} {c.numero_documento}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <Button type="button" variant="outline" onClick={() => abrirManual(false)} className="h-10 shrink-0 rounded-xl">
                            <Plus className="mr-1.5 size-4" /> Agregar manual
                        </Button>
                    </div>
                )}
                {error && !seleccionado && <p className="mt-2 text-xs text-destructive">{error}</p>}
            </div>

            {/* Modal manual */}
            {manualOpen && (
                <div className="fixed inset-0 z-[100] overflow-y-auto bg-black/50" onClick={() => setManualOpen(false)}>
                  <div className="flex min-h-full items-center justify-center p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-border bg-card p-5 shadow-soft" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-foreground">{value ? 'Editar cliente' : 'Agregar cliente'}</h3>
                            <button type="button" onClick={() => setManualOpen(false)} className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary"><X className="size-4" /></button>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Tipo doc.</Label>
                                <Combobox value={draft.tipo_doc} onChange={(v) => setDraft((d) => ({ ...d, tipo_doc: v }))} options={TIPO_DOC_OPTS} className="h-10 rounded-xl" />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Número de documento <span className="text-destructive">*</span></Label>
                                <div className="flex gap-1.5">
                                    <Input value={draft.num_doc} onChange={(e) => setDraft((d) => ({ ...d, num_doc: e.target.value }))} placeholder={draft.tipo_doc === '6' ? '20123456789' : '12345678'} className="h-10 rounded-xl" />
                                    <button type="button" onClick={lookupDraft} disabled={draftLoading || draft.num_doc.trim().length < 8} title="Consultar RUC / DNI"
                                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-border bg-secondary text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40">
                                        {draftLoading ? <Loader2 className="size-4 animate-spin" /> : <Search className="size-4" />}
                                    </button>
                                </div>
                            </div>
                            <div className="flex flex-col gap-1.5 sm:col-span-2">
                                <Label className="text-xs font-medium text-muted-foreground">Razón social / Nombre <span className="text-destructive">*</span></Label>
                                <Input value={draft.razon_social} onChange={(e) => setDraft((d) => ({ ...d, razon_social: e.target.value }))} placeholder="EMPRESA SAC" className="h-10 rounded-xl" />
                            </div>
                            {showDireccion && (
                                <div className="flex flex-col gap-1.5 sm:col-span-2">
                                    <Label className="text-xs font-medium text-muted-foreground">Dirección (opcional)</Label>
                                    <Input value={draft.direccion} onChange={(e) => setDraft((d) => ({ ...d, direccion: e.target.value }))} placeholder="Av. Los Olivos 123, Lima" className="h-10 rounded-xl" />
                                </div>
                            )}
                            {showEmail && (
                                <div className="flex flex-col gap-1.5 sm:col-span-2">
                                    <Label className="text-xs font-medium text-muted-foreground">Email (opcional)</Label>
                                    <Input type="email" value={draft.email} onChange={(e) => setDraft((d) => ({ ...d, email: e.target.value }))} placeholder="cliente@empresa.com" className="h-10 rounded-xl" />
                                </div>
                            )}
                        </div>

                        {draftError && <p className="mt-3 text-xs text-destructive">{draftError}</p>}

                        <div className="mt-5 flex flex-col gap-3 border-t border-border/60 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <span className="text-[11px] text-muted-foreground"><span className="text-destructive">*</span> Campos obligatorios</span>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="ghost" onClick={() => setManualOpen(false)}>Cancelar</Button>
                                <Button type="button" onClick={confirmarManual}>Usar cliente</Button>
                            </div>
                        </div>
                    </div>
                  </div>
                </div>
            )}
        </section>
    );
}
