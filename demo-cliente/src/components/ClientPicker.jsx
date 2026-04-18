import { useState } from 'react';
import { CLIENTES_DEMO } from '../data/productos.js';
import { api } from '../api/client.js';
import { Search, X, CheckCircle2, Loader2 } from 'lucide-react';

/**
 * Buscador de clientes — local primero, luego API SUNAT/RENIEC.
 */
export default function ClientPicker({ onSelect, onClose }) {
  const [query, setQuery] = useState('');
  const [searching, setSearching] = useState(false);
  const [apiResult, setApiResult] = useState(null);
  const [error, setError] = useState(null);

  const filtered = CLIENTES_DEMO.filter((c) => {
    const q = query.toLowerCase().trim();
    if (!q) return true;
    return (
      c.razon_social.toLowerCase().includes(q) ||
      c.num_doc.includes(q)
    );
  });

  async function buscarEnApi() {
    const numero = query.trim();
    if (!/^\d{8,11}$/.test(numero)) {
      setError('Ingresa un DNI (8 dígitos) o RUC (11 dígitos).');
      return;
    }
    const tipo = numero.length === 11 ? '6' : '1';
    setSearching(true);
    setError(null);
    setApiResult(null);
    try {
      const res = await api.buscarDocumento(tipo, numero);
      setApiResult(res.data);
    } catch (e) {
      setError(e.message || 'No se encontró el documento.');
    } finally {
      setSearching(false);
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={onClose}>
      <div className="bg-white rounded-xl w-full max-w-2xl max-h-[80vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
        <div className="p-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold flex items-center gap-2">
            <Search className="w-5 h-5" /> Seleccionar cliente
          </h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-700">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4 flex gap-2">
          <input
            type="text"
            autoFocus
            placeholder="RUC / DNI / Razón social..."
            className="input flex-1"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') buscarEnApi(); }}
          />
          <button onClick={buscarEnApi} disabled={searching} className="btn-secondary whitespace-nowrap flex items-center gap-2">
            {searching ? <><Loader2 className="w-4 h-4 animate-spin" /> Buscando...</> : 'Buscar SUNAT/RENIEC'}
          </button>
        </div>

        {error && <div className="mx-4 mt-3 p-3 bg-red-50 text-red-700 rounded-lg text-sm">{error}</div>}

        {apiResult && (
          <div className="mx-4 mt-3 p-4 bg-green-50 rounded-lg">
            <div className="text-xs text-green-700 mb-1 flex items-center gap-1">
              <CheckCircle2 className="w-3 h-3" /> Encontrado vía {apiResult.fuente || 'API'}
            </div>
            <div className="font-semibold">{apiResult.razon_social}</div>
            <div className="text-sm text-slate-600">
              {apiResult.tipo_doc === '6' ? 'RUC' : 'DNI'}: {apiResult.num_doc}
            </div>
            {apiResult.direccion && <div className="text-sm text-slate-500">{apiResult.direccion}</div>}
            <button
              onClick={() => { onSelect(apiResult); onClose(); }}
              className="btn-primary text-sm mt-3"
            >
              Usar este cliente
            </button>
          </div>
        )}

        <div className="flex-1 overflow-auto">
          <div className="px-4 py-2 text-xs uppercase text-slate-400">Clientes frecuentes</div>
          <table className="table-std">
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Documento</th>
                <th>Razón Social</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((c) => (
                <tr key={c.num_doc} className="hover:bg-slate-50">
                  <td>{c.tipo_doc === '6' ? 'RUC' : c.tipo_doc === '1' ? 'DNI' : c.tipo_doc}</td>
                  <td className="font-mono">{c.num_doc}</td>
                  <td>{c.razon_social}</td>
                  <td>
                    <button
                      onClick={() => { onSelect(c); onClose(); }}
                      className="btn-primary text-xs py-1 px-3"
                    >
                      Usar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
