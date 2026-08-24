import { useState } from 'react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// ─── Catálogos SUNAT ───────────────────────────────────────────────
const UNIDADES_BIEN = [
    { code: 'NIU', label: 'NIU - Unidad' }, { code: 'KGM', label: 'KGM - Kilogramo' },
    { code: 'GRM', label: 'GRM - Gramo' }, { code: 'LTR', label: 'LTR - Litro' },
    { code: 'MTR', label: 'MTR - Metro' }, { code: 'MTK', label: 'MTK - Metro Cuadrado' },
    { code: 'MTQ', label: 'MTQ - Metro Cúbico' }, { code: 'TNE', label: 'TNE - Tonelada' },
    { code: 'GLI', label: 'GLI - Galón' }, { code: 'PK', label: 'PK  - Paquete' },
    { code: 'BX', label: 'BX  - Caja' }, { code: 'SET', label: 'SET - Juego/Conjunto' },
    { code: 'KT', label: 'KT  - Kit' },
];
const UNIDADES_SERVICIO = [
    { code: 'ZZ', label: 'ZZ  - Servicio' }, { code: 'HUR', label: 'HUR - Hora' },
    { code: 'DAY', label: 'DAY - Día' }, { code: 'MON', label: 'MON - Mes' },
    { code: 'C62', label: 'C62 - Sin unidad específica' },
];
const TIPOS_IGV = [
    { code: '10', label: 'Gravado - IGV 18%' }, { code: '20', label: 'Exonerado' },
    { code: '30', label: 'Inafecto' }, { code: '40', label: 'Exportación' },
];

export type TipoItem = 'bien' | 'servicio';

export type ItemRow = {
    tipo_item: TipoItem;
    codigo: string;
    cod_producto_sunat: string;
    descripcion: string;
    unidad: string;
    cantidad: number;
    precio_unitario: number;
    descuento_pct: number;
    tip_afe_igv: string;
};

export function defaultItem(): ItemRow {
    return { tipo_item: 'bien', codigo: '', cod_producto_sunat: '', descripcion: '', unidad: 'NIU', cantidad: 1, precio_unitario: 0, descuento_pct: 0, tip_afe_igv: '10' };
}

export function calcItem(item: ItemRow) {
    const base = item.cantidad * item.precio_unitario * (1 - item.descuento_pct / 100);
    const igv = item.tip_afe_igv === '10' ? base * 0.18 : 0;
    return { base, igv, total: base + igv };
}

function fmt(n: number, dec = 2) {
    return new Intl.NumberFormat('es-PE', { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(n);
}

type Props = {
    value: ItemRow[];
    onChange: (items: ItemRow[]) => void;
    moneda?: string;
    titulo?: string;
    error?: string;
};

/**
 * Editor de ítems unificado (lista + modal de alta/edición) para todos los
 * documentos. Misma lógica y diseño que Facturas/Boletas.
 */
export function ItemsEditor({ value, onChange, moneda = 'PEN', titulo = 'Descripción de bienes vendidos / servicios prestados', error }: Props) {
    const simbolo = moneda === 'USD' ? '$' : 'S/';

    const [itemModalOpen, setItemModalOpen] = useState(false);
    const [itemEditIndex, setItemEditIndex] = useState<number | null>(null);
    const [itemDraft, setItemDraft]         = useState<ItemRow>(defaultItem());
    const [itemError, setItemError]         = useState('');

    // Totales de los ítems (para el pie).
    const totals = value.reduce((acc, it) => {
        const { base, igv } = calcItem(it);
        if (it.tip_afe_igv === '10') { acc.gravadas += base; acc.igv += igv; }
        else if (it.tip_afe_igv === '20') acc.exoneradas += base;
        else if (it.tip_afe_igv === '30') acc.inafectas += base;
        else if (it.tip_afe_igv === '40') acc.exportacion += base;
        return acc;
    }, { gravadas: 0, exoneradas: 0, inafectas: 0, exportacion: 0, igv: 0 });
    const total = totals.gravadas + totals.exoneradas + totals.inafectas + totals.exportacion + totals.igv;

    function abrirItem(index: number | null) {
        setItemDraft(index !== null ? { ...value[index] } : defaultItem());
        setItemEditIndex(index);
        setItemError('');
        setItemModalOpen(true);
    }

    function setDraftItem<K extends keyof ItemRow>(field: K, v: ItemRow[K]) {
        setItemDraft((d) => {
            const u = { ...d, [field]: v };
            if (field === 'tipo_item') u.unidad = v === 'servicio' ? 'ZZ' : 'NIU';
            return u;
        });
    }

    function confirmarItem() {
        if (!itemDraft.descripcion.trim()) { setItemError('Ingresa la descripción del producto o servicio.'); return; }
        if (itemDraft.cantidad <= 0)       { setItemError('La cantidad debe ser mayor a 0.'); return; }
        if (itemDraft.precio_unitario <= 0) { setItemError('El precio unitario debe ser mayor a 0.'); return; }
        const limpio = { ...itemDraft, descripcion: itemDraft.descripcion.trim() };
        onChange(itemEditIndex === null ? [...value, limpio] : value.map((it, i) => (i === itemEditIndex ? limpio : it)));
        setItemModalOpen(false);
    }

    function removeItem(i: number) {
        onChange(value.filter((_, idx) => idx !== i));
    }

    return (
        <section className="rounded-2xl border border-border bg-card shadow-soft">
            <div className="flex items-center justify-between border-b border-border/60 px-5 py-3.5">
                <span className="text-sm font-semibold text-foreground">{titulo}</span>
                <button type="button" onClick={() => abrirItem(null)}
                    className="inline-flex items-center gap-1.5 rounded-xl border border-border bg-secondary px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted">
                    <Plus className="size-3.5" /> Agregar ítem
                </button>
            </div>

            {error && <div className="border-b border-destructive/20 bg-destructive/5 px-5 py-2 text-xs text-destructive">{error}</div>}

            {value.length === 0 ? (
                <button type="button" onClick={() => abrirItem(null)}
                    className="m-5 flex flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-border px-5 py-8 text-center transition-colors hover:bg-muted/40">
                    <Plus className="size-5 text-muted-foreground" />
                    <span className="text-sm font-medium text-foreground">Agregar producto o servicio</span>
                    <span className="text-xs text-muted-foreground">Ingresa cada ítem desde el formulario</span>
                </button>
            ) : (
                <ul className="divide-y divide-border/60">
                    {value.map((item, i) => {
                        const { total: t } = calcItem(item);
                        return (
                            <li key={i} className="flex items-start gap-3 px-5 py-3">
                                <span className={`mt-0.5 shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase ${item.tipo_item === 'servicio' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'}`}>
                                    {item.tipo_item === 'servicio' ? 'Serv.' : 'Bien'}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-foreground">{item.descripcion || '(Sin descripción)'}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {(item.codigo || item.cod_producto_sunat) && (
                                            <span className="text-foreground/70">{[item.codigo, item.cod_producto_sunat].filter(Boolean).join(' / ')} ·{' '}</span>
                                        )}
                                        {fmt(item.cantidad, 0)} {item.unidad} · {simbolo} {fmt(item.precio_unitario)} c/u
                                        {item.descuento_pct > 0 && ` · -${item.descuento_pct}%`}
                                        {item.tip_afe_igv === '10' ? ' · IGV 18%' : ' · Sin IGV'}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <span className="text-sm font-semibold tabular-nums text-foreground">{simbolo} {fmt(t)}</span>
                                    <button type="button" onClick={() => abrirItem(i)} title="Editar" className="rounded p-1 text-muted-foreground transition-colors hover:text-foreground"><Pencil className="size-3.5" /></button>
                                    <button type="button" onClick={() => removeItem(i)} title="Quitar" className="rounded p-1 text-muted-foreground transition-colors hover:text-destructive"><Trash2 className="size-3.5" /></button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {/* Subtotales */}
            {value.length > 0 && (
                <div className="border-t border-border/60 px-5 py-4">
                    <div className="ml-auto max-w-xs space-y-1.5">
                        {totals.gravadas > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">Op. Gravadas</span><span className="tabular-nums">{simbolo} {fmt(totals.gravadas)}</span></div>}
                        {totals.exoneradas > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">Op. Exoneradas</span><span className="tabular-nums">{simbolo} {fmt(totals.exoneradas)}</span></div>}
                        {totals.inafectas > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">Op. Inafectas</span><span className="tabular-nums">{simbolo} {fmt(totals.inafectas)}</span></div>}
                        {totals.exportacion > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">Op. Exportación</span><span className="tabular-nums">{simbolo} {fmt(totals.exportacion)}</span></div>}
                        {totals.igv > 0 && <div className="flex justify-between text-xs"><span className="text-muted-foreground">I.G.V. (18%)</span><span className="tabular-nums">{simbolo} {fmt(totals.igv)}</span></div>}
                        <div className="flex justify-between border-t border-border/60 pt-2 text-sm font-semibold"><span>Total</span><span className="tabular-nums">{simbolo} {fmt(total)}</span></div>
                    </div>
                </div>
            )}

            {/* Modal alta/edición */}
            {itemModalOpen && (
                <div className="fixed inset-0 z-[100] overflow-y-auto bg-black/50" onClick={() => setItemModalOpen(false)}>
                  <div className="flex min-h-full items-center justify-center p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-border bg-card p-5 shadow-soft" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-foreground">{itemEditIndex === null ? 'Agregar ítem' : 'Editar ítem'}</h3>
                            <button type="button" onClick={() => setItemModalOpen(false)} className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary"><X className="size-4" /></button>
                        </div>

                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Tipo</Label>
                                <div className="grid grid-cols-2 gap-1.5">
                                    {(['bien', 'servicio'] as const).map((t) => (
                                        <label key={t} className="flex cursor-pointer items-center gap-2 py-2 text-sm font-medium text-foreground">
                                            <input type="radio" name="item-tipo" checked={itemDraft.tipo_item === t} onChange={() => setDraftItem('tipo_item', t)}
                                                className="size-4 shrink-0 appearance-none rounded-full border-2 border-muted-foreground/40 bg-transparent transition-colors checked:border-primary checked:bg-primary checked:bg-clip-content checked:p-[3px]" />
                                            {t === 'bien' ? 'Bien' : 'Servicio'}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs font-medium text-muted-foreground">Descripción <span className="text-destructive">*</span></Label>
                                <Input value={itemDraft.descripcion} onChange={(e) => setDraftItem('descripcion', e.target.value)}
                                    placeholder={itemDraft.tipo_item === 'servicio' ? 'Ej: Servicio de consultoría' : 'Ej: Producto XYZ'} className="h-10 rounded-xl" />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Código interno (opcional)</Label>
                                    <Input value={itemDraft.codigo} onChange={(e) => setDraftItem('codigo', e.target.value)} placeholder="Ej: P001" maxLength={30} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Código producto SUNAT</Label>
                                    <Input value={itemDraft.cod_producto_sunat} onChange={(e) => setDraftItem('cod_producto_sunat', e.target.value)} placeholder="Ej: 10101500" className="h-10 rounded-xl" />
                                    <p className="text-xs leading-tight text-muted-foreground">Catálogo N.° 25. Obligatorio si tu RUC está en el padrón SUNAT.</p>
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Unidad de medida <span className="text-destructive">*</span></Label>
                                    <Combobox value={itemDraft.unidad} onChange={(v) => setDraftItem('unidad', v)} searchable placeholder="Selecciona unidad"
                                        options={(itemDraft.tipo_item === 'servicio' ? UNIDADES_SERVICIO : UNIDADES_BIEN).map((u) => ({ value: String(u.code), label: u.label }))} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Afectación IGV <span className="text-destructive">*</span></Label>
                                    <Combobox value={itemDraft.tip_afe_igv} onChange={(v) => setDraftItem('tip_afe_igv', v)} searchable placeholder="Selecciona afectación"
                                        options={TIPOS_IGV.map((t) => ({ value: String(t.code), label: t.label }))} className="h-10 rounded-xl" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Cantidad <span className="text-destructive">*</span></Label>
                                    <Input type="number" min={0.001} step="0.001" value={itemDraft.cantidad} onChange={(e) => setDraftItem('cantidad', parseFloat(e.target.value) || 0)} placeholder="Ej: 1" className="h-10 rounded-xl text-right" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Precio unitario ({simbolo}) <span className="text-destructive">*</span></Label>
                                    <Input type="number" min={0} step="0.01" value={itemDraft.precio_unitario || ''} onChange={(e) => setDraftItem('precio_unitario', parseFloat(e.target.value) || 0)} placeholder="0.00" className="h-10 rounded-xl text-right" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Descuento % (opcional)</Label>
                                    <Input type="number" min={0} max={100} step="0.01" value={itemDraft.descuento_pct || ''} onChange={(e) => setDraftItem('descuento_pct', parseFloat(e.target.value) || 0)} placeholder="0" className="h-10 rounded-xl text-right" />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label className="text-xs font-medium text-muted-foreground">Total</Label>
                                    <div className="flex h-10 items-center justify-end rounded-xl border border-input bg-muted/40 px-3 text-sm font-semibold tabular-nums">{simbolo} {fmt(calcItem(itemDraft).total)}</div>
                                </div>
                            </div>

                            {itemError && <p className="text-xs text-destructive">{itemError}</p>}
                        </div>

                        <div className="mt-5 flex flex-col gap-3 border-t border-border/60 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <span className="text-xs text-muted-foreground"><span className="text-destructive">*</span> Campos obligatorios</span>
                            <div className="flex gap-2">
                                <Button type="button" variant="ghost" onClick={() => setItemModalOpen(false)}>Cancelar</Button>
                                <Button type="button" onClick={confirmarItem}>{itemEditIndex === null ? 'Agregar' : 'Guardar'}</Button>
                            </div>
                        </div>
                    </div>
                  </div>
                </div>
            )}
        </section>
    );
}
