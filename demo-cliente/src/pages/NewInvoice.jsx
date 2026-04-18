import { useState } from 'react';
import { api } from '../api/client.js';
import ProductPicker from '../components/ProductPicker.jsx';
import ClientPicker from '../components/ClientPicker.jsx';
import ItemsTable from '../components/ItemsTable.jsx';
import ResponseModal from '../components/ResponseModal.jsx';
import { FileText, Plus, Loader2, Check } from 'lucide-react';
import ClientSelector from '../components/ClientSelector.jsx';

export default function NewInvoice() {
  const today = new Date().toISOString().split('T')[0];

  const [form, setForm] = useState({
    serie: 'F001',
    fecha_emision: today,
    tipo_moneda: 'PEN',
    forma_pago: 'Contado',
    observacion: '',
  });

  const [cliente, setCliente] = useState(null);
  const [items, setItems] = useState([]);
  const [showProductPicker, setShowProductPicker] = useState(false);
  const [showClientPicker, setShowClientPicker] = useState(false);
  const [sending, setSending] = useState(false);
  const [response, setResponse] = useState(null);
  const [error, setError] = useState(null);

  function addProduct(p) {
    const item = {
      codigo: p.codigo,
      cod_producto_sunat: p.cod_producto_sunat,
      descripcion: p.descripcion,
      unidad: p.unidad,
      cantidad: 1,
      precio_unitario: p.precio_unitario,
      tip_afe_igv: p.tip_afe_igv || '10',
    };
    if (p.icbper) {
      item.icbper = p.icbper;
      item.factor_icbper = p.factor_icbper;
    }
    setItems([...items, item]);
  }

  async function submit(e) {
    e.preventDefault();

    if (!cliente) {
      alert('Selecciona un cliente');
      return;
    }
    if (items.length === 0) {
      alert('Agrega al menos un producto');
      return;
    }

    const payload = {
      ...form,
      cliente: {
        tipo_doc: cliente.tipo_doc,
        num_doc: cliente.num_doc,
        razon_social: cliente.razon_social,
        direccion: cliente.direccion || '',
        email: cliente.email,
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
      const res = await api.crearFactura(payload);
      setResponse(res);
      // Reset form
      setCliente(null);
      setItems([]);
      setForm({ ...form, observacion: '' });
    } catch (e) {
      setError(e);
    } finally {
      setSending(false);
    }
  }

  return (
    <div>
      <h1 className="page-title">
        <FileText className="w-7 h-7" />
        Nueva Factura
      </h1>

      <form onSubmit={submit} className="space-y-6">
        {/* Datos del documento */}
        <div className="card">
          <h2 className="section-title">Datos del documento</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label className="label">Serie</label>
              <input
                className="input font-mono"
                value={form.serie}
                onChange={(e) => setForm({ ...form, serie: e.target.value.toUpperCase() })}
                maxLength={4}
                required
              />
            </div>
            <div>
              <label className="label">Fecha emisión</label>
              <input
                type="date"
                className="input"
                value={form.fecha_emision}
                onChange={(e) => setForm({ ...form, fecha_emision: e.target.value })}
                required
              />
            </div>
            <div>
              <label className="label">Moneda</label>
              <select
                className="input"
                value={form.tipo_moneda}
                onChange={(e) => setForm({ ...form, tipo_moneda: e.target.value })}
              >
                <option value="PEN">PEN (Soles)</option>
                <option value="USD">USD (Dólares)</option>
                <option value="EUR">EUR (Euros)</option>
              </select>
            </div>
            <div>
              <label className="label">Forma de pago</label>
              <select
                className="input"
                value={form.forma_pago}
                onChange={(e) => setForm({ ...form, forma_pago: e.target.value })}
              >
                <option value="Contado">Contado</option>
                <option value="Credito">Crédito</option>
              </select>
            </div>
          </div>
        </div>

        {/* Cliente */}
        <div className="card">
          <h2 className="section-title">Cliente</h2>
          <ClientSelector
            cliente={cliente}
            onOpenPicker={() => setShowClientPicker(true)}
            onClear={() => setCliente(null)}
            placeholder="Seleccionar cliente (RUC)..."
          />
          {cliente?.direccion && (
            <p className="text-xs text-slate-500 mt-2 pl-1">{cliente.direccion}</p>
          )}
        </div>

        {/* Items */}
        <div className="card">
          <div className="flex items-center justify-between mb-4">
            <h2 className="section-title mb-0">Productos / Servicios</h2>
            <button type="button" onClick={() => setShowProductPicker(true)} className="btn-primary text-sm flex items-center gap-2">
              <Plus className="w-4 h-4" /> Agregar producto
            </button>
          </div>
          <ItemsTable items={items} onChange={setItems} moneda={form.tipo_moneda} />
        </div>

        {/* Observaciones */}
        <div className="card">
          <label className="label">Observaciones (opcional)</label>
          <textarea
            className="input"
            rows={2}
            value={form.observacion}
            onChange={(e) => setForm({ ...form, observacion: e.target.value })}
            placeholder="Comentarios adicionales..."
          />
        </div>

        {/* Submit */}
        <div className="flex justify-end gap-2">
          <button type="submit" disabled={sending} className="btn-primary flex items-center gap-2">
            {sending ? <><Loader2 className="w-4 h-4 animate-spin" /> Emitiendo...</> : <><Check className="w-4 h-4" /> Emitir Factura</>}
          </button>
        </div>
      </form>

      {showProductPicker && <ProductPicker onSelect={addProduct} onClose={() => setShowProductPicker(false)} />}
      {showClientPicker && <ClientPicker onSelect={setCliente} onClose={() => setShowClientPicker(false)} />}
      {(response || error) && (
        <ResponseModal
          response={response}
          error={error}
          tipo="facturas"
          onClose={() => { setResponse(null); setError(null); }}
        />
      )}
    </div>
  );
}
