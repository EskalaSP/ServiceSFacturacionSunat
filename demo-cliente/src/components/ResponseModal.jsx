import { useEffect, useRef, useState } from 'react';
import {
  CheckCircle2,
  XCircle,
  X,
  Download,
  FileText,
  FileCode,
  FileArchive,
  Loader2,
  Printer,
} from 'lucide-react';
import { api } from '../api/client.js';

const PDF_FORMATS = [
  { value: 'ticket-80', label: 'Ticket 80mm', icon: Printer },
  { value: 'ticket-58', label: 'Ticket 58mm', icon: Printer },
  { value: 'a5', label: 'A5', icon: FileText },
  { value: 'a4', label: 'A4', icon: FileText },
];

// Guías no tienen CDR oficial SUNAT (se consulta estado)
const TIPOS_SIN_CDR = ['guias-remision'];

export default function ResponseModal({ response, error, onClose, tipo }) {
  const [pdfBlobUrl, setPdfBlobUrl] = useState(null);
  const [pdfFormat, setPdfFormat] = useState('ticket-80');
  const [loadingPdf, setLoadingPdf] = useState(false);
  const [downloading, setDownloading] = useState(null); // 'pdf' | 'xml' | 'cdr' | null
  const lastBlobRef = useRef(null);

  const success = !error && response?.success;
  const data = response?.data;
  const docId = data?.id;
  const numeroCompleto = data?.numero_completo || `${data?.serie}-${data?.correlativo}` || 'documento';
  const canShowPdf = success && docId && tipo;
  const hasCdr = canShowPdf && !TIPOS_SIN_CDR.includes(tipo);

  // Cleanup blob URLs al cerrar
  useEffect(() => {
    return () => {
      if (lastBlobRef.current) {
        URL.revokeObjectURL(lastBlobRef.current);
      }
    };
  }, []);

  // Cargar PDF por defecto al abrir modal
  useEffect(() => {
    if (canShowPdf) {
      loadPdf(pdfFormat);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canShowPdf, docId]);

  async function loadPdf(format) {
    if (!canShowPdf) return;
    setLoadingPdf(true);
    try {
      const blob = await api.descargarPdf(tipo, docId, format);
      if (lastBlobRef.current) URL.revokeObjectURL(lastBlobRef.current);
      const url = URL.createObjectURL(blob);
      lastBlobRef.current = url;
      setPdfBlobUrl(url);
      setPdfFormat(format);
    } catch (e) {
      console.error('Error cargando PDF:', e);
    } finally {
      setLoadingPdf(false);
    }
  }

  async function descargar(kind) {
    if (!docId) return;
    setDownloading(kind);
    try {
      let blob, filename;
      if (kind === 'pdf') {
        blob = await api.descargarPdf(tipo, docId, pdfFormat);
        filename = `${numeroCompleto}-${pdfFormat}.pdf`;
      } else if (kind === 'xml') {
        blob = await api.descargarXml(tipo, docId);
        filename = `${numeroCompleto}.xml`;
      } else if (kind === 'cdr') {
        blob = await api.descargarCdr(tipo, docId);
        filename = `R-${numeroCompleto}.zip`;
      }
      // Forzar descarga sin abrir pestaña
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

  if (!response && !error) return null;

  return (
    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl w-full max-w-4xl max-h-[95vh] flex flex-col shadow-2xl">
        {/* Header */}
        <div className={`px-5 py-4 ${success ? 'bg-green-50' : 'bg-red-50'} rounded-t-2xl`}>
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <div className={`text-xs font-extrabold uppercase tracking-wider flex items-center gap-1.5 ${success ? 'text-green-700' : 'text-red-700'}`}>
                {success ? <><CheckCircle2 className="w-4 h-4" /> Éxito</> : <><XCircle className="w-4 h-4" /> Error</>}
              </div>
              <h2 className="text-base md:text-lg font-bold text-slate-900 mt-1 tracking-tight">
                {success ? response.message : (error?.message || 'Error desconocido')}
              </h2>
            </div>
            <button onClick={onClose} className="text-slate-500 hover:text-slate-900 p-1 hover:bg-white/50 rounded-lg flex-shrink-0">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Body */}
        <div className="overflow-auto flex-1 p-5">
          {success && data && (
            <>
              {/* Info resumida — todo en 1 línea compacta */}
              <div className="mb-3 flex items-center gap-2 text-sm flex-wrap">
                <span className="font-bold text-blue-600 font-mono">
                  {numeroCompleto}
                </span>
                {data.cliente && (
                  <>
                    <span className="text-slate-300">·</span>
                    <span className="font-semibold text-slate-700 truncate max-w-[240px]">
                      {data.cliente.razon_social}
                    </span>
                  </>
                )}
                {(data.totales?.total ?? data.mto_imp_venta) !== undefined && (
                  <>
                    <span className="text-slate-300">·</span>
                    <span className="font-bold text-slate-900">
                      {data.tipo_moneda || 'PEN'} {parseFloat(data.totales?.total ?? data.mto_imp_venta).toFixed(2)}
                    </span>
                  </>
                )}
                {(data.sunat?.estado ?? data.sunat_status) && (
                  <EstadoBadge estado={data.sunat?.estado ?? data.sunat_status} />
                )}
              </div>

              {/* Visor PDF + acciones */}
              {canShowPdf && (
                <div className="rounded-xl overflow-hidden mb-4 bg-slate-50">
                  <div className="bg-slate-50 p-3 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div className="flex items-center gap-1.5 flex-wrap flex-1">
                      <span className="text-[10px] font-extrabold uppercase tracking-widest text-slate-500 mr-1">Formato:</span>
                      {PDF_FORMATS.map((f) => (
                        <button
                          key={f.value}
                          type="button"
                          onClick={() => loadPdf(f.value)}
                          disabled={loadingPdf}
                          className={`
                            inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all
                            ${pdfFormat === f.value
                              ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/30'
                              : 'bg-white text-slate-700 hover:bg-slate-100'}
                          `}
                        >
                          <f.icon className="w-3.5 h-3.5" /> {f.label}
                        </button>
                      ))}
                    </div>

                    <button
                      type="button"
                      onClick={() => descargar('pdf')}
                      disabled={downloading === 'pdf'}
                      className="btn-primary text-xs py-1.5 px-3"
                    >
                      {downloading === 'pdf' ? (
                        <><Loader2 className="w-3.5 h-3.5 animate-spin" /> Descargando...</>
                      ) : (
                        <><Download className="w-3.5 h-3.5" /> PDF</>
                      )}
                    </button>
                  </div>

                  <div className="relative bg-slate-100" style={{ height: '500px' }}>
                    {loadingPdf && (
                      <div className="absolute inset-0 flex items-center justify-center bg-white/80 z-10">
                        <Loader2 className="w-6 h-6 animate-spin text-blue-600" />
                      </div>
                    )}
                    {pdfBlobUrl ? (
                      <iframe
                        src={pdfBlobUrl}
                        title={`PDF ${numeroCompleto}`}
                        className="w-full h-full border-0"
                      />
                    ) : !loadingPdf && (
                      <div className="flex items-center justify-center h-full text-slate-400 text-sm">
                        PDF no disponible
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Otros archivos */}
              {canShowPdf && (
                <div className="flex flex-wrap gap-2 mb-4">
                  <button
                    type="button"
                    onClick={() => descargar('xml')}
                    disabled={downloading === 'xml'}
                    className="btn-secondary text-sm"
                  >
                    {downloading === 'xml' ? (
                      <><Loader2 className="w-4 h-4 animate-spin" /> Descargando XML...</>
                    ) : (
                      <><FileCode className="w-4 h-4" /> Descargar XML</>
                    )}
                  </button>

                  {hasCdr && (
                    <button
                      type="button"
                      onClick={() => descargar('cdr')}
                      disabled={downloading === 'cdr'}
                      className="btn-secondary text-sm"
                    >
                      {downloading === 'cdr' ? (
                        <><Loader2 className="w-4 h-4 animate-spin" /> Descargando CDR...</>
                      ) : (
                        <><FileArchive className="w-4 h-4" /> Descargar CDR</>
                      )}
                    </button>
                  )}
                </div>
              )}
            </>
          )}

          {error && error.errors && (
            <div className="bg-red-50 p-4 rounded-xl mb-4">
              <div className="text-sm font-bold text-red-800 mb-2">Errores de validación:</div>
              <pre className="text-xs text-red-700 whitespace-pre-wrap font-mono">
                {JSON.stringify(error.errors, null, 2)}
              </pre>
            </div>
          )}

          <details className="mt-3">
            <summary className="cursor-pointer text-xs font-bold uppercase tracking-wider text-slate-400 hover:text-slate-700 select-none">
              Ver respuesta completa (JSON)
            </summary>
            <pre className="mt-2 p-3 bg-slate-900 text-green-300 text-xs rounded-lg overflow-auto max-h-60 font-mono">
              {JSON.stringify(success ? response : error?.data || { error: error?.message }, null, 2)}
            </pre>
          </details>
        </div>

        {/* Footer */}
        <div className="p-4 flex justify-end rounded-b-2xl bg-slate-50">
          <button onClick={onClose} className="btn-primary">
            Cerrar
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
    aceptado: 'bg-green-100 text-green-800',
    rechazado: 'bg-red-100 text-red-800',
    anulado: 'bg-gray-100 text-gray-800',
    anulacion_en_proceso: 'bg-orange-100 text-orange-800',
  };
  return (
    <span className={`badge ${colors[estado] || 'bg-slate-100 text-slate-700'}`}>
      {estado}
    </span>
  );
}
