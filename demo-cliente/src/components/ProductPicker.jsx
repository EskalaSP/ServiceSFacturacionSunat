import { useState } from 'react';
import { PRODUCTOS_DEMO } from '../data/productos.js';
import { Search, X } from 'lucide-react';

/**
 * Modal con buscador de productos. Al seleccionar, llama onSelect(producto).
 */
export default function ProductPicker({ onSelect, onClose }) {
  const [query, setQuery] = useState('');

  const filtered = PRODUCTOS_DEMO.filter((p) => {
    const q = query.toLowerCase().trim();
    if (!q) return true;
    return (
      p.descripcion.toLowerCase().includes(q) ||
      p.codigo.toLowerCase().includes(q) ||
      p.categoria.toLowerCase().includes(q)
    );
  });

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={onClose}>
      <div className="bg-white rounded-xl w-full max-w-3xl max-h-[80vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
        <div className="p-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold flex items-center gap-2">
            <Search className="w-5 h-5" /> Seleccionar producto
          </h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-700">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4">
          <input
            type="text"
            autoFocus
            placeholder="Buscar por nombre, código o categoría..."
            className="input"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>

        <div className="flex-1 overflow-auto">
          {filtered.length === 0 ? (
            <div className="p-8 text-center text-slate-400">Sin resultados</div>
          ) : (
            <table className="table-std">
              <thead className="sticky top-0">
                <tr>
                  <th>Código</th>
                  <th>Descripción</th>
                  <th>Und</th>
                  <th className="text-right">Precio</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((p) => (
                  <tr key={p.codigo} className="hover:bg-slate-50">
                    <td className="font-mono text-xs">{p.codigo}</td>
                    <td>
                      <div>{p.descripcion}</div>
                      <div className="text-xs text-slate-400">{p.categoria}</div>
                    </td>
                    <td className="text-xs">{p.unidad}</td>
                    <td className="text-right font-semibold">S/ {p.precio_unitario.toFixed(2)}</td>
                    <td>
                      <button
                        onClick={() => { onSelect(p); onClose(); }}
                        className="btn-primary text-xs py-1 px-3"
                      >
                        Agregar
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}
