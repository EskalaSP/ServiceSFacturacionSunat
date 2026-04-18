import { X } from 'lucide-react';

/**
 * Tabla de ítems editable con cálculo automático de IGV/Total.
 */
export default function ItemsTable({ items, onChange, moneda = 'PEN' }) {
  const simbolo = moneda === 'USD' ? '$' : moneda === 'EUR' ? '€' : 'S/';

  function updateItem(idx, field, value) {
    const updated = [...items];
    updated[idx] = { ...updated[idx], [field]: value };
    onChange(updated);
  }

  function removeItem(idx) {
    const updated = items.filter((_, i) => i !== idx);
    onChange(updated);
  }

  // Cálculo rápido
  const totales = items.reduce((acc, it) => {
    const cantidad = parseFloat(it.cantidad) || 0;
    const precio = parseFloat(it.precio_unitario) || 0;
    const subtotal = cantidad * precio;
    const afe = it.tip_afe_igv || '10';
    let igv = 0;
    let baseGravada = 0;
    let baseNoGravada = 0;

    if (afe === '10') {
      baseGravada = subtotal / 1.18;
      igv = subtotal - baseGravada;
    } else if (afe === '20' || afe === '30') {
      baseNoGravada = subtotal;
    }

    acc.subtotal += subtotal;
    acc.baseGravada += baseGravada;
    acc.baseNoGravada += baseNoGravada;
    acc.igv += igv;
    return acc;
  }, { subtotal: 0, baseGravada: 0, baseNoGravada: 0, igv: 0 });

  return (
    <div>
      {items.length === 0 ? (
        <div className="text-center py-10 text-slate-400 bg-slate-50 rounded-xl font-medium">
          Sin productos. Haz clic en "Agregar producto" arriba.
        </div>
      ) : (
        <div className="table-wrap">
          <table className="table-std min-w-[700px]">
            <thead>
              <tr>
                <th>Descripción</th>
                <th className="w-20">Und</th>
                <th className="w-24 text-right">Cantidad</th>
                <th className="w-28 text-right">Precio Unit.</th>
                <th className="w-28">IGV</th>
                <th className="w-28 text-right">Total</th>
                <th className="w-12"></th>
              </tr>
            </thead>
            <tbody>
              {items.map((it, idx) => {
                const total = (parseFloat(it.cantidad) || 0) * (parseFloat(it.precio_unitario) || 0);
                return (
                  <tr key={idx}>
                    <td>
                      <input
                        className="input-inline"
                        value={it.descripcion || ''}
                        onChange={(e) => updateItem(idx, 'descripcion', e.target.value)}
                      />
                      {it.codigo && <div className="text-xs text-slate-400 px-2 font-semibold mt-0.5">{it.codigo}</div>}
                    </td>
                    <td>
                      <input
                        className="input-inline font-mono text-xs"
                        value={it.unidad || ''}
                        onChange={(e) => updateItem(idx, 'unidad', e.target.value)}
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        min="0"
                        step="any"
                        className="input-inline text-right"
                        value={it.cantidad || ''}
                        onChange={(e) => updateItem(idx, 'cantidad', e.target.value)}
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        min="0"
                        step="any"
                        className="input-inline text-right"
                        value={it.precio_unitario || ''}
                        onChange={(e) => updateItem(idx, 'precio_unitario', e.target.value)}
                      />
                    </td>
                    <td>
                      <select
                        className="input-inline text-xs"
                        value={it.tip_afe_igv || '10'}
                        onChange={(e) => updateItem(idx, 'tip_afe_igv', e.target.value)}
                      >
                        <option value="10">Gravado</option>
                        <option value="20">Exonerado</option>
                        <option value="30">Inafecto</option>
                        <option value="40">Exportación</option>
                      </select>
                    </td>
                    <td className="text-right font-bold text-slate-900">{simbolo} {total.toFixed(2)}</td>
                    <td>
                      <button
                        type="button"
                        onClick={() => removeItem(idx)}
                        className="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded-md transition-colors"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {items.length > 0 && (
        <div className="mt-5 flex justify-end">
          <div className="w-full sm:w-80 bg-slate-50 rounded-xl p-4 space-y-2 text-sm">
            {totales.baseGravada > 0 && (
              <div className="flex justify-between">
                <span className="text-slate-600 font-medium">Op. Gravadas:</span>
                <span className="font-semibold">{simbolo} {totales.baseGravada.toFixed(2)}</span>
              </div>
            )}
            {totales.baseNoGravada > 0 && (
              <div className="flex justify-between">
                <span className="text-slate-600 font-medium">Op. Exoneradas/Inafectas:</span>
                <span className="font-semibold">{simbolo} {totales.baseNoGravada.toFixed(2)}</span>
              </div>
            )}
            {totales.igv > 0 && (
              <div className="flex justify-between">
                <span className="text-slate-600 font-medium">IGV (18%):</span>
                <span className="font-semibold">{simbolo} {totales.igv.toFixed(2)}</span>
              </div>
            )}
            <div className="flex justify-between pt-3 mt-2 text-lg">
              <span className="font-extrabold text-slate-900">TOTAL:</span>
              <span className="font-extrabold text-blue-600">{simbolo} {totales.subtotal.toFixed(2)}</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
