import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api/client.js';
import {
  FileText,
  Receipt,
  TrendingDown,
  TrendingUp,
  Truck,
  Search,
  Loader2,
  XCircle,
  FileCode,
  FileArchive,
  Download,
} from 'lucide-react';

const LABELS = {
  'facturas': { titulo: 'Facturas', Icon: FileText, method: 'listarFacturas', hasCdr: true },
  'boletas': { titulo: 'Boletas', Icon: Receipt, method: 'listarBoletas', hasCdr: true },
  'notas-credito': { titulo: 'Notas de Crédito', Icon: TrendingDown, method: 'listarNotasCredito', hasCdr: true },
  'notas-debito': { titulo: 'Notas de Débito', Icon: TrendingUp, method: 'listarNotasDebito', hasCdr: true },
  'guias-remision': { titulo: 'Guías de Remisión', Icon: Truck, method: 'listarGuias', hasCdr: false },
};

export default function DocumentList() {
  const { tipo } = useParams();
  const config = LABELS[tipo] || LABELS['facturas'];
  const Icon = config.Icon;
  const [docs, setDocs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [filtro, setFiltro] = useState({ estado: '', buscar: '' });

  useEffect(() => {
    loadDocs();
  }, [tipo]);

  // docId_kind en proceso de descarga (ej: "123_pdf", "123_xml")
  const [downloading, setDownloading] = useState(null);

  async function loadDocs() {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      if (filtro.estado) params.append('estado', filtro.estado);
      if (filtro.buscar) params.append('buscar', filtro.buscar);
      const query = params.toString() ? `?${params.toString()}` : '';
      const res = await api[config.method](query);
      setDocs(Array.isArray(res.data) ? res.data : (res.data?.data || []));
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  async function descargar(doc, kind) {
    const key = `${doc.id}_${kind}`;
    setDownloading(key);
    try {
      let blob;
      const numero = doc.numero_completo || `${doc.serie}-${doc.correlativo}`;
      let filename;
      if (kind === 'pdf') {
        blob = await api.descargarPdf(tipo, doc.id, 'a4');
        filename = `${numero}.pdf`;
      } else if (kind === 'xml') {
        blob = await api.descargarXml(tipo, doc.id);
        filename = `${numero}.xml`;
      } else if (kind === 'cdr') {
        blob = await api.descargarCdr(tipo, doc.id);
        filename = `R-${numero}.zip`;
      }
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setTimeout(() => URL.revokeObjectURL(url), 500);
    } catch (e) {
      alert(`Error al descargar ${kind.toUpperCase()}: ${e.message}`);
    } finally {
      setDownloading(null);
    }
  }

  return (
    <div>
      <h1 className="page-title">
        <Icon className="w-7 h-7" />
        {config.titulo}
      </h1>

      <div className="card mb-4">
        <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
          <div className="flex-1">
            <label className="label">Buscar</label>
            <input
              className="input"
              placeholder="Serie, correlativo, cliente..."
              value={filtro.buscar}
              onChange={(e) => setFiltro({ ...filtro, buscar: e.target.value })}
            />
          </div>
          <div>
            <label className="label">Estado SUNAT</label>
            <select
              className="input"
              value={filtro.estado}
              onChange={(e) => setFiltro({ ...filtro, estado: e.target.value })}
            >
              <option value="">Todos</option>
              <option value="pendiente">Pendiente</option>
              <option value="enviado">Enviado</option>
              <option value="aceptado">Aceptado</option>
              <option value="rechazado">Rechazado</option>
              <option value="anulado">Anulado</option>
            </select>
          </div>
          <button onClick={loadDocs} className="btn-primary flex items-center gap-2">
            <Search className="w-4 h-4" /> Filtrar
          </button>
        </div>
      </div>

      <div className="card">
        {loading ? (
          <div className="text-center py-8 text-slate-400 flex items-center justify-center gap-2">
            <Loader2 className="w-5 h-5 animate-spin" /> Cargando...
          </div>
        ) : error ? (
          <div className="p-4 bg-red-50 text-red-700 rounded-lg flex items-center gap-2">
            <XCircle className="w-5 h-5" /> {error}
          </div>
        ) : docs.length === 0 ? (
          <div className="text-center py-8 text-slate-400">Sin documentos</div>
        ) : (
          <div className="table-wrap">
          <table className="table-std">
            <thead>
              <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th className="text-right">Total</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {docs.map((d) => {
                // El API devuelve datos anidados: d.totales.total, d.sunat.estado
                const total = d.totales?.total ?? d.mto_imp_venta ?? d.monto_total ?? 0;
                const estado = d.sunat?.estado ?? d.sunat_status ?? null;
                const clienteNombre = d.cliente?.razon_social
                  ?? d.client_razon_social
                  ?? d.destinatario?.razon_social
                  ?? '-';

                return (
                <tr key={d.id}>
                  <td className="font-mono font-semibold text-slate-900">{d.numero_completo || `${d.serie}-${d.correlativo}`}</td>
                  <td className="text-slate-600">{d.fecha_emision?.slice(0, 10)}</td>
                  <td className="truncate max-w-xs">{clienteNombre}</td>
                  <td className="text-right font-bold text-slate-900">
                    {d.tipo_moneda || 'PEN'} {parseFloat(total).toFixed(2)}
                  </td>
                  <td><EstadoBadge estado={estado} /></td>
                  <td>
                    <div className="flex items-center gap-1">
                      <ActionButton
                        onClick={() => descargar(d, 'pdf')}
                        loading={downloading === `${d.id}_pdf`}
                        Icon={FileText}
                        label="PDF"
                        color="text-blue-600 hover:bg-blue-50"
                      />
                      <ActionButton
                        onClick={() => descargar(d, 'xml')}
                        loading={downloading === `${d.id}_xml`}
                        Icon={FileCode}
                        label="XML"
                        color="text-slate-600 hover:bg-slate-100"
                      />
                      {config.hasCdr && (
                        <ActionButton
                          onClick={() => descargar(d, 'cdr')}
                          loading={downloading === `${d.id}_cdr`}
                          Icon={FileArchive}
                          label="CDR"
                          color="text-amber-600 hover:bg-amber-50"
                        />
                      )}
                    </div>
                  </td>
                </tr>
                );
              })}
            </tbody>
          </table>
          </div>
        )}
      </div>
    </div>
  );
}

function ActionButton({ onClick, loading, Icon, label, color }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={loading}
      title={`Descargar ${label}`}
      className={`inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-md transition-colors disabled:opacity-50 disabled:cursor-wait ${color}`}
    >
      {loading ? (
        <Loader2 className="w-3.5 h-3.5 animate-spin" />
      ) : (
        <Icon className="w-3.5 h-3.5" />
      )}
      {label}
    </button>
  );
}

function EstadoBadge({ estado }) {
  const colors = {
    pendiente: 'bg-yellow-100 text-yellow-800',
    enviado: 'bg-blue-100 text-blue-800',
    aceptado: 'bg-green-100 text-green-800',
    rechazado: 'bg-red-100 text-red-800',
    anulado: 'bg-gray-100 text-gray-800',
  };
  return <span className={`px-2 py-1 rounded text-xs font-semibold ${colors[estado] || 'bg-slate-100'}`}>{estado || '-'}</span>;
}
