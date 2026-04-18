import { useEffect, useState } from 'react';
import { api } from '../api/client.js';
import {
  FileStack,
  Plus,
  Loader2,
  RefreshCcw,
  Send,
  Ban,
  CheckCircle2,
  XCircle,
  Search,
  X,
} from 'lucide-react';

const TODAY = new Date().toISOString().split('T')[0];

export default function Summaries() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [refreshing, setRefreshing] = useState(null); // id del que se está refrescando

  async function loadSummaries() {
    setLoading(true);
    setError(null);
    try {
      const res = await api.listarResumenes();
      setItems(res.data || []);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadSummaries();
  }, []);

  async function refrescar(id) {
    setRefreshing(id);
    try {
      await api.estadoResumen(id);
      await loadSummaries();
    } catch (e) {
      alert('Error al refrescar: ' + e.message);
    } finally {
      setRefreshing(null);
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6 gap-3 flex-wrap">
        <h1 className="page-title mb-0">
          <FileStack className="w-7 h-7" />
          Resúmenes Diarios
        </h1>
        <button onClick={() => setShowModal(true)} className="btn-primary">
          <Plus className="w-4 h-4" /> Nuevo resumen
        </button>
      </div>

      <div className="card">
        <div className="flex items-center justify-between mb-3">
          <h2 className="section-title mb-0">Historial</h2>
          <button onClick={loadSummaries} disabled={loading} className="btn-ghost text-sm">
            <RefreshCcw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> Actualizar
          </button>
        </div>

        <div className="table-wrap">
          {loading ? (
            <div className="text-center py-10 text-slate-400 flex items-center justify-center gap-2">
              <Loader2 className="w-5 h-5 animate-spin" /> Cargando...
            </div>
          ) : error ? (
            <div className="p-4 bg-red-50 text-red-700 rounded-xl flex items-center gap-2">
              <XCircle className="w-5 h-5" /> {error}
            </div>
          ) : items.length === 0 ? (
            <div className="text-center py-10 text-slate-400">
              Sin resúmenes. Crea el primero con el botón "Nuevo resumen".
            </div>
          ) : (
            <table className="table-std min-w-[700px]">
              <thead>
                <tr>
                  <th>Identificador</th>
                  <th>Tipo</th>
                  <th>Fecha ref.</th>
                  <th className="text-right">Docs</th>
                  <th>Estado SUNAT</th>
                  <th>Ticket</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {items.map((s) => (
                  <tr key={s.id}>
                    <td className="font-mono font-bold text-slate-900">{s.identifier}</td>
                    <td>
                      <span className={`badge ${s.tipo === 'anulacion' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`}>
                        {s.tipo === 'anulacion' ? <><Ban className="w-3 h-3 mr-1 inline" /> Anulación</> : <><Send className="w-3 h-3 mr-1 inline" /> Envío</>}
                      </span>
                    </td>
                    <td className="text-slate-600">{s.fecha_referencia}</td>
                    <td className="text-right font-bold">{s.total_documentos}</td>
                    <td><EstadoBadge estado={s.estado_sunat} /></td>
                    <td className="text-xs font-mono text-slate-500">{s.ticket || '—'}</td>
                    <td>
                      <button
                        type="button"
                        onClick={() => refrescar(s.id)}
                        disabled={refreshing === s.id}
                        className="text-blue-600 hover:bg-blue-50 px-2 py-1 rounded-md text-xs font-bold inline-flex items-center gap-1"
                      >
                        {refreshing === s.id ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <RefreshCcw className="w-3.5 h-3.5" />}
                        Refrescar
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {showModal && (
        <NewSummaryModal
          onClose={() => setShowModal(false)}
          onSuccess={() => { setShowModal(false); loadSummaries(); }}
        />
      )}
    </div>
  );
}

// ──────────────────────────────────────────────────────────────────
// Modal: Nuevo Resumen
// ──────────────────────────────────────────────────────────────────
function NewSummaryModal({ onClose, onSuccess }) {
  const [modo, setModo] = useState('envio'); // 'envio' | 'anulacion'
  const [fecha, setFecha] = useState(TODAY);
  const [boletasParaAnular, setBoletasParaAnular] = useState([]);
  const [boletasDisponibles, setBoletasDisponibles] = useState([]);
  const [loadingBoletas, setLoadingBoletas] = useState(false);
  const [sending, setSending] = useState(false);
  const [response, setResponse] = useState(null);
  const [error, setError] = useState(null);

  // Carga boletas aceptadas para modo anulación
  useEffect(() => {
    if (modo === 'anulacion') {
      setLoadingBoletas(true);
      api.listarBoletas('?estado=aceptado&por_pagina=50')
        .then((res) => {
          const data = res.data?.data || [];
          setBoletasDisponibles(data);
        })
        .catch((e) => setError(e.message))
        .finally(() => setLoadingBoletas(false));
    }
  }, [modo]);

  function toggleBoleta(b) {
    const exists = boletasParaAnular.find((x) => x.id === b.id);
    if (exists) {
      setBoletasParaAnular(boletasParaAnular.filter((x) => x.id !== b.id));
    } else {
      setBoletasParaAnular([...boletasParaAnular, { id: b.id, motivo: '', numero: b.numero_completo }]);
    }
  }

  function updateMotivo(id, motivo) {
    setBoletasParaAnular(boletasParaAnular.map((b) => (b.id === id ? { ...b, motivo } : b)));
  }

  async function submit() {
    setError(null);
    setResponse(null);

    if (modo === 'anulacion') {
      if (boletasParaAnular.length === 0) {
        setError('Selecciona al menos una boleta para anular');
        return;
      }
      const sinMotivo = boletasParaAnular.find((b) => !b.motivo.trim());
      if (sinMotivo) {
        setError(`Falta motivo para boleta ${sinMotivo.numero}`);
        return;
      }
    }

    const payload = {
      fecha_resumen: fecha,
      ...(modo === 'anulacion' && {
        anular: boletasParaAnular.map((b) => ({ id: b.id, motivo: b.motivo, tipo_documento: '03' })),
      }),
    };

    setSending(true);
    try {
      const res = await api.crearResumen(payload);
      setResponse(res);
    } catch (e) {
      setError(e.message);
    } finally {
      setSending(false);
    }
  }

  if (response) {
    return (
      <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div className="bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl">
          <div className="flex items-center gap-2 text-green-700 font-extrabold uppercase text-xs tracking-wider mb-2">
            <CheckCircle2 className="w-5 h-5" /> Éxito
          </div>
          <h2 className="text-lg font-bold mb-3">{response.message}</h2>
          <div className="bg-slate-50 rounded-xl p-4 space-y-1 text-sm">
            <div className="flex justify-between">
              <span className="text-slate-500">Identificador:</span>
              <span className="font-mono font-bold">{response.data?.identifier}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Acción:</span>
              <span className="font-semibold capitalize">{response.data?.accion}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Documentos:</span>
              <span className="font-bold">{response.data?.total_documentos}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Estado:</span>
              <EstadoBadge estado={response.data?.estado_sunat} />
            </div>
          </div>
          <button onClick={onSuccess} className="btn-primary w-full mt-4">
            Cerrar
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl">
        <div className="p-5 flex items-center justify-between">
          <h2 className="text-lg font-bold flex items-center gap-2">
            <FileStack className="w-5 h-5" /> Nuevo Resumen
          </h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-700">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-5 overflow-auto flex-1">
          {/* Selector de modo */}
          <div className="grid grid-cols-2 gap-3 mb-5">
            <button
              type="button"
              onClick={() => setModo('envio')}
              className={`p-4 rounded-xl text-left transition-all ${
                modo === 'envio'
                  ? 'bg-blue-100 ring-2 ring-blue-500'
                  : 'bg-slate-100 hover:bg-slate-200/60'
              }`}
            >
              <Send className={`w-5 h-5 mb-1 ${modo === 'envio' ? 'text-blue-600' : 'text-slate-400'}`} />
              <div className="font-bold text-sm">Envío</div>
              <div className="text-xs text-slate-500 mt-0.5">Enviar boletas pendientes del día a SUNAT</div>
            </button>
            <button
              type="button"
              onClick={() => setModo('anulacion')}
              className={`p-4 rounded-xl text-left transition-all ${
                modo === 'anulacion'
                  ? 'bg-red-100 ring-2 ring-red-500'
                  : 'bg-slate-100 hover:bg-slate-200/60'
              }`}
            >
              <Ban className={`w-5 h-5 mb-1 ${modo === 'anulacion' ? 'text-red-600' : 'text-slate-400'}`} />
              <div className="font-bold text-sm">Anulación</div>
              <div className="text-xs text-slate-500 mt-0.5">Anular boletas ya aceptadas por SUNAT</div>
            </button>
          </div>

          {/* Fecha */}
          <div className="mb-4">
            <label className="label">
              {modo === 'envio' ? 'Fecha de boletas a enviar' : 'Fecha del resumen'}
            </label>
            <input
              type="date"
              className="input"
              value={fecha}
              onChange={(e) => setFecha(e.target.value)}
              max={TODAY}
            />
            <p className="text-xs text-slate-500 mt-1">
              {modo === 'envio'
                ? 'Toma todas las boletas con esa fecha en estado pendiente'
                : 'Plazo: hasta 7 días desde la emisión de las boletas'}
            </p>
          </div>

          {/* Modo anulación: listado de boletas */}
          {modo === 'anulacion' && (
            <div>
              <label className="label">Boletas aceptadas disponibles para anular</label>
              {loadingBoletas ? (
                <div className="text-center py-6 text-slate-400 flex items-center justify-center gap-2">
                  <Loader2 className="w-4 h-4 animate-spin" /> Cargando boletas...
                </div>
              ) : boletasDisponibles.length === 0 ? (
                <div className="text-center py-6 text-slate-400 bg-slate-50 rounded-xl">
                  No hay boletas aceptadas para anular
                </div>
              ) : (
                <div className="bg-slate-50 rounded-xl max-h-72 overflow-auto">
                  {boletasDisponibles.map((b) => {
                    const seleccionada = boletasParaAnular.find((x) => x.id === b.id);
                    return (
                      <div key={b.id} className={`p-3 ${seleccionada ? 'bg-red-50' : ''}`}>
                        <label className="flex items-start gap-3 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={!!seleccionada}
                            onChange={() => toggleBoleta(b)}
                            className="mt-1 w-4 h-4 rounded text-red-600 focus:ring-red-500"
                          />
                          <div className="flex-1 min-w-0">
                            <div className="flex items-baseline gap-2 flex-wrap">
                              <span className="font-mono font-bold text-slate-900">{b.numero_completo}</span>
                              <span className="text-xs text-slate-500">
                                {b.fecha_emision?.slice(0, 10)}
                              </span>
                              <span className="font-bold text-slate-700">
                                {b.tipo_moneda || 'PEN'} {parseFloat(b.totales?.total ?? 0).toFixed(2)}
                              </span>
                            </div>
                            <div className="text-xs text-slate-500 truncate">
                              {b.cliente?.razon_social || '—'}
                            </div>
                          </div>
                        </label>
                        {seleccionada && (
                          <input
                            type="text"
                            className="input mt-2 ml-7"
                            placeholder="Motivo de la anulación..."
                            value={seleccionada.motivo}
                            onChange={(e) => updateMotivo(b.id, e.target.value)}
                            maxLength={255}
                          />
                        )}
                      </div>
                    );
                  })}
                </div>
              )}
              {boletasParaAnular.length > 0 && (
                <p className="text-xs text-slate-600 mt-2 font-semibold">
                  {boletasParaAnular.length} boleta{boletasParaAnular.length !== 1 && 's'} seleccionada{boletasParaAnular.length !== 1 && 's'}
                </p>
              )}
            </div>
          )}

          {error && (
            <div className="mt-4 p-3 bg-red-50 rounded-xl text-sm text-red-700 flex items-center gap-2">
              <XCircle className="w-4 h-4 flex-shrink-0" />
              <span>{error}</span>
            </div>
          )}
        </div>

        <div className="p-4 flex justify-end gap-2 bg-slate-50 rounded-b-2xl">
          <button onClick={onClose} className="btn-secondary text-sm">Cancelar</button>
          <button
            onClick={submit}
            disabled={sending}
            className={modo === 'anulacion' ? 'btn-danger text-sm' : 'btn-primary text-sm'}
          >
            {sending ? (
              <><Loader2 className="w-4 h-4 animate-spin" /> Enviando...</>
            ) : modo === 'anulacion' ? (
              <><Ban className="w-4 h-4" /> Crear anulación</>
            ) : (
              <><Send className="w-4 h-4" /> Crear resumen</>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

function EstadoBadge({ estado }) {
  const colors = {
    pendiente: 'bg-yellow-100 text-yellow-800',
    enviado: 'bg-blue-100 text-blue-800',
    procesando: 'bg-blue-100 text-blue-800',
    aceptado: 'bg-green-100 text-green-800',
    rechazado: 'bg-red-100 text-red-800',
    anulado: 'bg-gray-100 text-gray-800',
  };
  return <span className={`badge ${colors[estado] || 'bg-slate-100'}`}>{estado || '—'}</span>;
}
