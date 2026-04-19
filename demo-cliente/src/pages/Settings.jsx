import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getConfig, saveConfig } from '../api/client.js';
import {
  Settings as SettingsIcon,
  Save,
  Plug,
  CheckCircle2,
  XCircle,
  Lightbulb,
  ArrowRight,
  Loader2,
  AlertTriangle,
} from 'lucide-react';

export default function Settings() {
  const navigate = useNavigate();
  const [config, setConfig] = useState(getConfig());
  const [saved, setSaved] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState(null);

  function handleSave() {
    saveConfig(config);
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  }

  async function handleTest() {
    saveConfig(config);
    setTesting(true);
    setTestResult(null);
    try {
      const response = await fetch(`${config.base_url.replace(/\/$/, '')}/empresa`, {
        headers: {
          'Accept': 'application/json',
          'X-Api-Key': config.api_key,
          'X-Api-Secret': config.api_secret,
        },
      });
      const data = await response.json();
      if (response.ok && data.success) {
        setTestResult({ success: true, empresa: data.data });
      } else {
        setTestResult({ success: false, error: data.message || `Error ${response.status}` });
      }
    } catch (e) {
      setTestResult({ success: false, error: e.message });
    } finally {
      setTesting(false);
    }
  }

  return (
    <div className="max-w-2xl">
      <h1 className="page-title mb-2">
        <SettingsIcon className="w-7 h-7" />
        Configuración de la API
      </h1>
      <p className="text-slate-600 mb-6">
        Ingresa las credenciales de tu empresa para conectarte con la API SUNAT.
        Si aún no tienes, regístrate primero con <code className="bg-slate-100 px-2 py-0.5 rounded text-sm">POST /v1/registro</code>.
      </p>

      <div className="card space-y-4">
        <div>
          <label className="label">URL Base de la API</label>
          <input
            className="input"
            value={config.base_url}
            onChange={(e) => setConfig({ ...config, base_url: e.target.value })}
            placeholder="https://api.kodevo.es/sunat-api/api/v1"
          />
          <p className="text-xs text-slate-500 mt-1">Ejemplo: https://api.kodevo.es/sunat-api/api/v1 o tu URL propia</p>
        </div>

        <div>
          <label className="label">X-Api-Key</label>
          <input
            className="input font-mono"
            value={config.api_key}
            onChange={(e) => setConfig({ ...config, api_key: e.target.value })}
            placeholder="Tu api_key de 64 caracteres"
          />
        </div>

        <div>
          <label className="label">X-Api-Secret</label>
          <input
            type="password"
            className="input font-mono"
            value={config.api_secret}
            onChange={(e) => setConfig({ ...config, api_secret: e.target.value })}
            placeholder="Tu api_secret"
          />
          <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
            <AlertTriangle className="w-3 h-3" /> Se guarda en localStorage de tu navegador.
          </p>
        </div>

        <div className="flex gap-2 pt-4 items-center">
          <button onClick={handleSave} className="btn-primary flex items-center gap-2">
            <Save className="w-4 h-4" /> Guardar
          </button>
          <button
            onClick={handleTest}
            disabled={testing || !config.api_key || !config.api_secret}
            className="btn-secondary flex items-center gap-2"
          >
            {testing ? (
              <><Loader2 className="w-4 h-4 animate-spin" /> Probando...</>
            ) : (
              <><Plug className="w-4 h-4" /> Probar conexión</>
            )}
          </button>
          {saved && (
            <span className="text-sm text-green-600 flex items-center gap-1">
              <CheckCircle2 className="w-4 h-4" /> Guardado
            </span>
          )}
        </div>

        {testResult && (
          <div className={`p-4 rounded-lg ${testResult.success ? 'bg-green-50' : 'bg-red-50'}`}>
            {testResult.success ? (
              <>
                <div className="font-semibold text-green-800 flex items-center gap-2">
                  <CheckCircle2 className="w-5 h-5" /> Conexión exitosa
                </div>
                <div className="mt-2 text-sm space-y-1">
                  <div><strong>Empresa:</strong> {testResult.empresa.razon_social}</div>
                  <div><strong>RUC:</strong> {testResult.empresa.ruc}</div>
                  <div><strong>Plan:</strong> {testResult.empresa.plan}</div>
                  <div><strong>Entorno:</strong> {testResult.empresa.environment}</div>
                </div>
                <button onClick={() => navigate('/')} className="btn-primary text-sm mt-3 flex items-center gap-2">
                  Ir al Dashboard <ArrowRight className="w-4 h-4" />
                </button>
              </>
            ) : (
              <>
                <div className="font-semibold text-red-800 flex items-center gap-2">
                  <XCircle className="w-5 h-5" /> Error de conexión
                </div>
                <div className="mt-2 text-sm text-red-700">{testResult.error}</div>
              </>
            )}
          </div>
        )}
      </div>

      <div className="mt-6 p-4 bg-blue-50 rounded-lg text-sm">
        <div className="font-semibold text-blue-900 mb-1 flex items-center gap-2">
          <Lightbulb className="w-4 h-4" /> ¿Cómo obtener mis credenciales?
        </div>
        <ol className="list-decimal list-inside space-y-1 text-blue-800">
          <li>Registra tu empresa con <code className="bg-white px-1.5 rounded">POST /api/v1/registro</code></li>
          <li>La respuesta incluye <code className="bg-white px-1.5 rounded">api_key</code> y <code className="bg-white px-1.5 rounded">api_secret</code></li>
          <li>Guárdalos aquí para empezar a emitir documentos</li>
        </ol>
      </div>
    </div>
  );
}
