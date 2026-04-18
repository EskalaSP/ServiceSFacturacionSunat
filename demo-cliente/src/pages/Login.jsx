import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { login } from '../api/client.js';
import { FileDigit, LogIn, XCircle, Zap, ShieldCheck, Sparkles, Info } from 'lucide-react';

export default function Login() {
  const navigate = useNavigate();
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  function handleSubmit(e) {
    e.preventDefault();
    setLoading(true);
    // Pequeño delay para sentir responsive
    setTimeout(() => {
      const ok = login(usuario, password);
      if (!ok) {
        setError('Usuario o contraseña incorrectos');
        setLoading(false);
        return;
      }
      navigate('/');
    }, 250);
  }

  return (
    <div className="min-h-screen flex bg-white">
      {/* ═══════════ PANEL IZQUIERDO — Marca ═══════════ */}
      <div className="hidden lg:flex lg:w-[45%] relative overflow-hidden p-12 flex-col justify-between" style={{ background: 'rgb(15 23 42)' }}>
        {/* Formas decorativas (colores sólidos con alpha, no gradientes) */}
        <div
          className="absolute rounded-full pointer-events-none"
          style={{ top: '-120px', right: '-120px', width: '380px', height: '380px', background: 'rgb(37 99 235 / 0.25)' }}
        />
        <div
          className="absolute rounded-full pointer-events-none"
          style={{ bottom: '-80px', left: '-100px', width: '320px', height: '320px', background: 'rgb(59 130 246 / 0.18)' }}
        />
        <div
          className="absolute rounded-full pointer-events-none"
          style={{ top: '35%', left: '55%', width: '180px', height: '180px', background: 'rgb(96 165 250 / 0.12)' }}
        />

        {/* Header */}
        <div className="relative z-10 flex items-center gap-3 text-white">
          <div className="w-11 h-11 rounded-2xl flex items-center justify-center" style={{ background: 'rgb(37 99 235)' }}>
            <FileDigit className="w-6 h-6" />
          </div>
          <div>
            <div className="text-lg font-extrabold tracking-tight">SUNAT Demo</div>
            <div className="text-xs text-slate-400 font-medium">Sistema de facturación</div>
          </div>
        </div>

        {/* Hero */}
        <div className="relative z-10 text-white">
          <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold tracking-wide mb-6" style={{ background: 'rgb(37 99 235 / 0.25)', color: 'rgb(147 197 253)' }}>
            <Sparkles className="w-3.5 h-3.5" />
            Demo interactiva
          </div>
          <h2 className="text-4xl xl:text-5xl font-extrabold leading-[1.1] tracking-tight mb-4">
            Facturación electrónica <span className="text-blue-400">sin complicaciones.</span>
          </h2>
          <p className="text-slate-300 text-base leading-relaxed max-w-md">
            Emite facturas, boletas, notas de crédito y guías de remisión conectándote directamente a SUNAT. Todo desde una única API.
          </p>

          {/* Features */}
          <div className="mt-10 space-y-4">
            <Feature Icon={Zap} title="Emisión en segundos" subtitle="Envío directo a SUNAT o en modo lote" />
            <Feature Icon={ShieldCheck} title="Certificado digital" subtitle="Firma XML + validación SUNAT incluida" />
          </div>
        </div>

        {/* Footer */}
        <div className="relative z-10 flex items-center justify-between text-xs text-slate-400">
          <div>Hecho con ♥ en Perú</div>
          <div className="font-mono">v1.0.0</div>
        </div>
      </div>

      {/* ═══════════ PANEL DERECHO — Formulario ═══════════ */}
      <div className="flex-1 flex items-center justify-center p-6 sm:p-10">
        <div className="w-full max-w-sm">
          {/* Logo móvil */}
          <div className="lg:hidden flex flex-col items-center mb-8">
            <div className="w-14 h-14 rounded-2xl flex items-center justify-center text-white mb-3" style={{ background: 'rgb(37 99 235)' }}>
              <FileDigit className="w-7 h-7" />
            </div>
            <div className="text-lg font-extrabold tracking-tight text-slate-900">SUNAT Demo</div>
          </div>

          <div className="mb-8">
            <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 mb-1.5">
              Bienvenido 👋
            </h1>
            <p className="text-slate-500 text-sm">Inicia sesión para acceder a la demo.</p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="label">Usuario</label>
              <input
                autoFocus
                className="input"
                value={usuario}
                onChange={(e) => { setUsuario(e.target.value); setError(null); }}
                placeholder="demo"
                required
              />
            </div>
            <div>
              <label className="label">Contraseña</label>
              <input
                type="password"
                className="input"
                value={password}
                onChange={(e) => { setPassword(e.target.value); setError(null); }}
                placeholder="demo123"
                required
              />
            </div>

            {error && (
              <div className="p-3 bg-red-50 rounded-xl text-sm text-red-700 flex items-center gap-2">
                <XCircle className="w-4 h-4 flex-shrink-0" /> {error}
              </div>
            )}

            <button type="submit" disabled={loading} className="btn-primary w-full mt-2" style={{ padding: '0.75rem 1rem' }}>
              {loading ? (
                <><span className="inline-block w-4 h-4 rounded-full animate-spin" style={{ border: '2px solid rgb(255 255 255 / 0.4)', borderTopColor: 'white' }} /> Entrando...</>
              ) : (
                <><LogIn className="w-4 h-4" /> Entrar</>
              )}
            </button>
          </form>

          {/* Credenciales demo */}
          <div className="mt-6 p-4 rounded-xl bg-slate-100">
            <div className="flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-widest text-slate-600 mb-2">
              <Info className="w-3 h-3" /> Credenciales demo
            </div>
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div>
                <div className="text-[10px] text-slate-500 font-semibold uppercase">Usuario</div>
                <code className="block mt-0.5 px-2 py-1 bg-white rounded-md font-mono font-bold text-slate-900">demo</code>
              </div>
              <div>
                <div className="text-[10px] text-slate-500 font-semibold uppercase">Contraseña</div>
                <code className="block mt-0.5 px-2 py-1 bg-white rounded-md font-mono font-bold text-slate-900">demo123</code>
              </div>
            </div>
          </div>

          <div className="mt-6 text-center text-xs text-slate-400">
            © {new Date().getFullYear()} SUNAT Demo · Solo para pruebas
          </div>
        </div>
      </div>
    </div>
  );
}

function Feature({ Icon, title, subtitle }) {
  return (
    <div className="flex items-start gap-3">
      <div className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style={{ background: 'rgb(37 99 235 / 0.2)' }}>
        <Icon className="w-5 h-5 text-blue-300" />
      </div>
      <div>
        <div className="font-bold text-white text-sm">{title}</div>
        <div className="text-xs text-slate-400 mt-0.5">{subtitle}</div>
      </div>
    </div>
  );
}
