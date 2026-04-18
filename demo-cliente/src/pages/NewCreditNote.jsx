import { useState } from 'react';
import { api } from '../api/client.js';
import ProductPicker from '../components/ProductPicker.jsx';
import ClientPicker from '../components/ClientPicker.jsx';
import ItemsTable from '../components/ItemsTable.jsx';
import ResponseModal from '../components/ResponseModal.jsx';
import { TrendingDown, Plus, Loader2, Check } from 'lucide-react';
import ClientSelector from '../components/ClientSelector.jsx';

const MOTIVOS_NC = [
  { cod: '01', desc: 'Anulación de la operación' },
  { cod: '02', desc: 'Anulación por error en el RUC' },
  { cod: '03', desc: 'Corrección por error en la descripción' },
  { cod: '04', desc: 'Descuento global' },
  { cod: '05', desc: 'Descuento por ítem' },
  { cod: '06', desc: 'Devolución total/parcial' },
  { cod: '07', desc: 'Bonificación/Descuento' },
  { cod: '08', desc: 'Disminución en el valor' },
  { cod: '09', desc: 'Otros' },
];

export default function NewCreditNote() {
  const today = new Date().toISOString().split('T')[0];

  const [form, setForm] = useState({
    serie: 'FC01',
    fecha_emision: today,
    tipo_moneda: 'PEN',
    doc_afectado_tipo: '01',
    doc_afectado_serie: 'F001',
    doc_afectado_correlativo: '',
    cod_motivo: '06',
    des_motivo: '',
  });

  const [cliente, setCliente] = useState(null);
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
      unidad: p.unidad,
      cantidad: 1,
      precio_unitario: p.precio_unitario,
      tip_afe_igv: p.tip_afe_igv || '10',
    }]);
  }

  async function submit(e) {
    e.preventDefault();
    if (!cliente) { alert('Selecciona un cliente'); return; }
    if (items.length === 0) { alert('Agrega al menos un ítem'); return; }
    if (!form.des_motivo.trim()) { alert('Describe el motivo'); return; }

    const payload = {
      ...form,
      cliente: {
        tipo_doc: cliente.tipo_doc,
        num_doc: cliente.num_doc,
        razon_social: cliente.razon_social,
        direccion: cliente.direccion || '',
      },
      items: items.map((it) => ({
        ...it,
        cantidad: parseFloat(it.cantidad),
        precio_unitario: parseFloat(it.precio_unitario),
      })),
    };

    setSending(true);
    setResponse(null);
    setError(null);

    try {
      const res = await api.crearNotaCredito(payload);
      setResponse(res);
      setItems([]);
      setForm({ ...form, doc_afectado_correlativo: '', des_motivo: '' });
    } catch (e) {
      setError(e);
    } finally {
      setSending(false);
    }
  }

  return (
    <div>
      <h1 className="page-title">
        <TrendingDown className="w-7 h-7" />
        Nueva Nota de Crédito
      </h1>

      <form onSubmit={submit} className="space-y-6">
        <div className="card">
          <h2 className="section-title">Datos del documento</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label className="label">Serie NC</label>
              <input className="input font-mono" value={form.serie} onChange={(e) => setForm({ ...form, serie: e.target.value.toUpperCase() })} maxLength={4} required />
              <p className="text-xs text-slate-500 mt-1">Ej: FC01 (para factura), BC01 (para boleta)</p>
            </div>
            <div>
              <label className="label">Fecha</label>
              <input type="date" className="input" value={form.fecha_emision} onChange={(e) => setForm({ ...form, fecha_emision: e.target.value })} required />
            </div>
            <div>
              <label className="label">Moneda</label>
              <select className="input" value={form.tipo_moneda} onChange={(e) => setForm({ ...form, tipo_moneda: e.target.value })}>
                <option value="PEN">PEN</option>
                <option value="USD">USD</option>
              </select>
            </div>
          </div>
        </div>

        <div className="card">
          <h2 className="section-title">Documento afectado</h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="label">Tipo</label>
              <select className="input" value={form.doc_afectado_tipo} onChange={(e) => setForm({ ...form, doc_afectado_tipo: e.target.value })}>
                <option value="01">01 - Factura</option>
                <option value="03">03 - Boleta</option>
                <option value="12">12 - Ticket</option>
              </select>
            </div>
            <div>
              <label className="label">Serie</label>
              <input className="input font-mono" value={form.doc_afectado_serie} onChange={(e) => setForm({ ...form, doc_afectado_serie: e.target.value.toUpperCase() })} maxLength={4} required />
            </div>
            <div>
              <label className="label">Correlativo</label>
              <input className="input" value={form.doc_afectado_correlativo} onChange={(e) => setForm({ ...form, doc_afectado_correlativo: e.target.value })} placeholder="123" required />
            </div>
          </div>
        </div>

        <div className="card">
          <h2 className="section-title">Motivo de la nota</h2>
          <div className="space-y-4">
            <div>
              <label className="label">Código motivo (Cat. 09)</label>
              <select className="input" value={form.cod_motivo} onChange={(e) => setForm({ ...form, cod_motivo: e.target.value })}>
                {MOTIVOS_NC.map((m) => (
                  <option key={m.cod} value={m.cod}>{m.cod} - {m.desc}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">Descripción del motivo</label>
              <textarea className="input" rows={2} value={form.des_motivo} onChange={(e) => setForm({ ...form, des_motivo: e.target.value })} placeholder="Ej: Devolución por defecto de fábrica" required maxLength={250} />
            </div>
          </div>
        </div>

        <div className="card">
          <h2 className="section-title">Cliente</h2>
          <ClientSelector
            cliente={cliente}
            onOpenPicker={() => setShowClientPicker(true)}
            onClear={() => setCliente(null)}
            placeholder="Cliente del documento original..."
          />
        </div>

        <div className="card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="section-title mb-0">Ítems afectados</h2>
            <button type="button" onClick={() => setShowProductPicker(true)} className="btn-primary text-sm flex items-center gap-2"><Plus className="w-4 h-4" /> Agregar</button>
          </div>
          <ItemsTable items={items} onChange={setItems} moneda={form.tipo_moneda} />
          <p className="text-xs text-slate-500 mt-3">
            Los ítems representan lo que se está revertiendo. Para anulación total, incluye los mismos ítems de la factura original.
          </p>
        </div>

        <div className="flex justify-end">
          <button type="submit" disabled={sending} className="btn-primary flex items-center gap-2">
            {sending ? <><Loader2 className="w-4 h-4 animate-spin" /> Emitiendo...</> : <><Check className="w-4 h-4" /> Emitir Nota de Crédito</>}
          </button>
        </div>
      </form>

      {showProductPicker && <ProductPicker onSelect={addProduct} onClose={() => setShowProductPicker(false)} />}
      {showClientPicker && <ClientPicker onSelect={setCliente} onClose={() => setShowClientPicker(false)} />}
      {(response || error) && (
        <ResponseModal response={response} error={error} tipo="notas-credito" onClose={() => { setResponse(null); setError(null); }} />
      )}
    </div>
  );
}
