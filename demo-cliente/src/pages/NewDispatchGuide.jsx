import { useState } from 'react';
import { api } from '../api/client.js';
import ProductPicker from '../components/ProductPicker.jsx';
import ClientPicker from '../components/ClientPicker.jsx';
import ResponseModal from '../components/ResponseModal.jsx';
import { Truck, Plus, Loader2, Check, Car, X } from 'lucide-react';
import ClientSelector from '../components/ClientSelector.jsx';

const MOTIVOS_TRASLADO = [
  { cod: '01', desc: 'Venta' },
  { cod: '02', desc: 'Compra' },
  { cod: '04', desc: 'Traslado entre establecimientos' },
  { cod: '08', desc: 'Importación' },
  { cod: '09', desc: 'Exportación' },
  { cod: '13', desc: 'Otros' },
];

export default function NewDispatchGuide() {
  const today = new Date().toISOString().split('T')[0];
  const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

  const [form, setForm] = useState({
    serie: 'T001',
    fecha_emision: today,
    observacion: '',
    cod_traslado: '01',
    mod_traslado: '02',
    fecha_traslado: tomorrow,
    peso_total: 10,
    und_peso_total: 'KGM',
    num_bultos: 1,
    partida_ubigeo: '150101',
    partida_direccion: 'AV. LIMA 123',
    llegada_ubigeo: '150101',
    llegada_direccion: '',
    vehiculo_placa: 'ABC-123',
    conductor_num_doc: '',
    conductor_nombres: '',
    conductor_apellidos: '',
    conductor_licencia: '',
    transportista_num_doc: '',
    transportista_razon_social: '',
  });

  const [destinatario, setDestinatario] = useState(null);
  const [items, setItems] = useState([]);
  const [showProductPicker, setShowProductPicker] = useState(false);
  const [showClientPicker, setShowClientPicker] = useState(false);
  const [sending, setSending] = useState(false);
  const [response, setResponse] = useState(null);
  const [error, setError] = useState(null);

  function addProduct(p) {
    setItems([...items, {
      codigo: p.codigo,
      descripcion: p.descripcion,
      cantidad: 1,
      unidad: p.unidad,
    }]);
  }

  function updateItem(idx, field, value) {
    const updated = [...items];
    updated[idx] = { ...updated[idx], [field]: value };
    setItems(updated);
  }

  async function submit(e) {
    e.preventDefault();
    if (!destinatario) { alert('Selecciona un destinatario'); return; }
    if (items.length === 0) { alert('Agrega al menos un producto'); return; }

    const payload = {
      serie: form.serie,
      fecha_emision: form.fecha_emision,
      observacion: form.observacion || undefined,
      destinatario: {
        tipo_doc: destinatario.tipo_doc,
        num_doc: destinatario.num_doc,
        razon_social: destinatario.razon_social,
      },
      cod_traslado: form.cod_traslado,
      mod_traslado: form.mod_traslado,
      fecha_traslado: form.fecha_traslado,
      peso_total: parseFloat(form.peso_total),
      und_peso_total: form.und_peso_total,
      num_bultos: parseInt(form.num_bultos, 10) || 1,
      partida_ubigeo: form.partida_ubigeo,
      partida_direccion: form.partida_direccion,
      llegada_ubigeo: form.llegada_ubigeo,
      llegada_direccion: form.llegada_direccion,
      items: items.map((it) => ({
        descripcion: it.descripcion,
        cantidad: parseFloat(it.cantidad),
        unidad: it.unidad,
        codigo: it.codigo,
      })),
    };

    if (form.mod_traslado === '02') {
      payload.vehiculo = { placa: form.vehiculo_placa };
      payload.conductor = {
        tipo_doc: '1',
        num_doc: form.conductor_num_doc,
        tipo: 'Principal',
        nombres: form.conductor_nombres,
        apellidos: form.conductor_apellidos,
        licencia: form.conductor_licencia,
      };
    } else {
      payload.transportista = {
        tipo_doc: '6',
        num_doc: form.transportista_num_doc,
        razon_social: form.transportista_razon_social,
      };
    }

    setSending(true);
    setResponse(null);
    setError(null);

    try {
      const res = await api.crearGuia(payload);
      setResponse(res);
      setItems([]);
    } catch (e) {
      setError(e);
    } finally {
      setSending(false);
    }
  }

  return (
    <div>
      <h1 className="page-title">
        <Truck className="w-7 h-7" />
        Nueva Guía de Remisión
      </h1>

      <form onSubmit={submit} className="space-y-6">
        <div className="card">
          <h2 className="section-title">Datos de la guía</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label className="label">Serie</label>
              <input className="input font-mono" value={form.serie} onChange={(e) => setForm({ ...form, serie: e.target.value.toUpperCase() })} maxLength={4} required />
            </div>
            <div>
              <label className="label">Fecha emisión</label>
              <input type="date" className="input" value={form.fecha_emision} onChange={(e) => setForm({ ...form, fecha_emision: e.target.value })} required />
            </div>
            <div>
              <label className="label">Fecha traslado</label>
              <input type="date" className="input" value={form.fecha_traslado} onChange={(e) => setForm({ ...form, fecha_traslado: e.target.value })} required />
            </div>
            <div>
              <label className="label">Motivo (Cat. 20)</label>
              <select className="input" value={form.cod_traslado} onChange={(e) => setForm({ ...form, cod_traslado: e.target.value })}>
                {MOTIVOS_TRASLADO.map((m) => (
                  <option key={m.cod} value={m.cod}>{m.cod} - {m.desc}</option>
                ))}
              </select>
            </div>
          </div>
        </div>

        <div className="card">
          <h2 className="section-title">Destinatario</h2>
          <ClientSelector
            cliente={destinatario}
            onOpenPicker={() => setShowClientPicker(true)}
            onClear={() => setDestinatario(null)}
            placeholder="Seleccionar destinatario (RUC o DNI)..."
          />
        </div>

        <div className="card">
          <h2 className="section-title">Traslado</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label className="label">Modalidad</label>
              <select className="input" value={form.mod_traslado} onChange={(e) => setForm({ ...form, mod_traslado: e.target.value })}>
                <option value="02">02 - Privado (tu vehículo)</option>
                <option value="01">01 - Público (transportista)</option>
              </select>
            </div>
            <div className="grid grid-cols-3 gap-2">
              <div>
                <label className="label">Peso total</label>
                <input type="number" min="0.01" step="0.01" className="input" value={form.peso_total} onChange={(e) => setForm({ ...form, peso_total: e.target.value })} required />
              </div>
              <div>
                <label className="label">Unidad</label>
                <select className="input" value={form.und_peso_total} onChange={(e) => setForm({ ...form, und_peso_total: e.target.value })}>
                  <option value="KGM">KGM</option>
                  <option value="TNE">TNE</option>
                </select>
              </div>
              <div>
                <label className="label">Bultos</label>
                <input type="number" min="1" className="input" value={form.num_bultos} onChange={(e) => setForm({ ...form, num_bultos: e.target.value })} />
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="label">Partida - Ubigeo</label>
              <input className="input font-mono" value={form.partida_ubigeo} onChange={(e) => setForm({ ...form, partida_ubigeo: e.target.value })} maxLength={6} required />
              <label className="label mt-2">Partida - Dirección</label>
              <input className="input" value={form.partida_direccion} onChange={(e) => setForm({ ...form, partida_direccion: e.target.value })} required />
            </div>
            <div>
              <label className="label">Llegada - Ubigeo</label>
              <input className="input font-mono" value={form.llegada_ubigeo} onChange={(e) => setForm({ ...form, llegada_ubigeo: e.target.value })} maxLength={6} required />
              <label className="label mt-2">Llegada - Dirección</label>
              <input className="input" value={form.llegada_direccion} onChange={(e) => setForm({ ...form, llegada_direccion: e.target.value })} placeholder="Dirección destino" required />
            </div>
          </div>
        </div>

        {form.mod_traslado === '02' ? (
          <div className="card">
            <h2 className="font-semibold mb-4 flex items-center gap-2"><Car className="w-5 h-5" /> Vehículo + Conductor (traslado privado)</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="label">Placa</label>
                <input className="input font-mono" value={form.vehiculo_placa} onChange={(e) => setForm({ ...form, vehiculo_placa: e.target.value.toUpperCase() })} maxLength={10} required placeholder="ABC-123" />
              </div>
            </div>
            <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="label">DNI conductor</label>
                <input className="input font-mono" value={form.conductor_num_doc} onChange={(e) => setForm({ ...form, conductor_num_doc: e.target.value })} maxLength={8} required />
              </div>
              <div>
                <label className="label">Licencia</label>
                <input className="input font-mono" value={form.conductor_licencia} onChange={(e) => setForm({ ...form, conductor_licencia: e.target.value })} required />
              </div>
              <div>
                <label className="label">Nombres</label>
                <input className="input" value={form.conductor_nombres} onChange={(e) => setForm({ ...form, conductor_nombres: e.target.value })} required />
              </div>
              <div>
                <label className="label">Apellidos</label>
                <input className="input" value={form.conductor_apellidos} onChange={(e) => setForm({ ...form, conductor_apellidos: e.target.value })} required />
              </div>
            </div>
          </div>
        ) : (
          <div className="card">
            <h2 className="font-semibold mb-4 flex items-center gap-2"><Truck className="w-5 h-5" /> Transportista (traslado público)</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="label">RUC transportista</label>
                <input className="input font-mono" value={form.transportista_num_doc} onChange={(e) => setForm({ ...form, transportista_num_doc: e.target.value })} maxLength={11} required />
              </div>
              <div>
                <label className="label">Razón social</label>
                <input className="input" value={form.transportista_razon_social} onChange={(e) => setForm({ ...form, transportista_razon_social: e.target.value })} required />
              </div>
            </div>
          </div>
        )}

        <div className="card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="section-title mb-0">Productos a trasladar</h2>
            <button type="button" onClick={() => setShowProductPicker(true)} className="btn-primary text-sm flex items-center gap-2"><Plus className="w-4 h-4" /> Agregar</button>
          </div>
          {items.length === 0 ? (
            <div className="text-center py-8 text-slate-400 bg-slate-50 rounded-lg">
              Sin productos
            </div>
          ) : (
            <div className="table-wrap"><table className="table-std">
              <thead>
                <tr>
                  <th>Descripción</th>
                  <th className="w-20">Und</th>
                  <th className="w-24 text-right">Cantidad</th>
                  <th className="w-12"></th>
                </tr>
              </thead>
              <tbody>
                {items.map((it, idx) => (
                  <tr key={idx}>
                    <td>{it.descripcion}</td>
                    <td className="font-mono text-xs">{it.unidad}</td>
                    <td>
                      <input type="number" min="0" step="any" className="input-inline text-right" value={it.cantidad} onChange={(e) => updateItem(idx, 'cantidad', e.target.value)} />
                    </td>
                    <td>
                      <button type="button" onClick={() => setItems(items.filter((_, i) => i !== idx))} className="text-red-500 hover:text-red-700"><X className="w-4 h-4" /></button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table></div>
          )}
        </div>

        <div className="card">
          <label className="label">Observaciones</label>
          <textarea className="input" rows={2} value={form.observacion} onChange={(e) => setForm({ ...form, observacion: e.target.value })} />
        </div>

        <div className="flex justify-end">
          <button type="submit" disabled={sending} className="btn-primary flex items-center gap-2">
            {sending ? <><Loader2 className="w-4 h-4 animate-spin" /> Emitiendo...</> : <><Check className="w-4 h-4" /> Emitir Guía</>}
          </button>
        </div>
      </form>

      {showProductPicker && <ProductPicker onSelect={addProduct} onClose={() => setShowProductPicker(false)} />}
      {showClientPicker && <ClientPicker onSelect={setDestinatario} onClose={() => setShowClientPicker(false)} />}
      {(response || error) && (
        <ResponseModal response={response} error={error} tipo="guias-remision" onClose={() => { setResponse(null); setError(null); }} />
      )}
    </div>
  );
}
