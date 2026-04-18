import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { isConfigured, isLoggedIn, getSession, logout } from '../api/client.js';
import { useEffect, useState } from 'react';
import {
  LayoutDashboard,
  FileText,
  Receipt,
  TrendingDown,
  TrendingUp,
  Truck,
  ClipboardList,
  Settings,
  FileDigit,
  Menu,
  X,
  FileStack,
  LogOut,
} from 'lucide-react';

const LINK_CLASS = ({ isActive }) =>
  `flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 ${
    isActive
      ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/30'
      : 'text-slate-700 hover:bg-slate-100'
  }`;

const IC = 'w-[18px] h-[18px] flex-shrink-0';

export default function Layout() {
  const navigate = useNavigate();
  const location = useLocation();
  const configured = isConfigured();
  const logged = isLoggedIn();
  const session = getSession();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Sin sesión → login
  useEffect(() => {
    if (!logged) {
      navigate('/login');
      return;
    }
    if (!configured && location.pathname !== '/configuracion') {
      navigate('/configuracion');
    }
  }, [logged, configured, navigate, location.pathname]);

  // Cerrar sidebar al cambiar de ruta (en móvil)
  useEffect(() => {
    setSidebarOpen(false);
  }, [location.pathname]);

  function handleLogout() {
    logout();
    navigate('/login');
  }

  return (
    <div className="min-h-screen flex bg-slate-50">
      {/* Overlay móvil */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-30 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`
          fixed lg:sticky top-0 left-0 z-40 w-72 h-screen bg-white flex flex-col
          transform transition-transform duration-300 ease-in-out
          ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
          lg:translate-x-0
        `}
      >
        <div className="px-5 py-5 flex items-center justify-between">
          <div>
            <h1 className="text-base font-extrabold text-slate-900 flex items-center gap-2 tracking-tight">
              <span className="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center text-white">
                <FileDigit className="w-5 h-5" />
              </span>
              SUNAT Demo
            </h1>
            <p className="text-xs text-slate-500 mt-1 font-medium">Sistema ejemplo de facturación</p>
          </div>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden text-slate-400 hover:text-slate-900 p-1"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <nav className="flex-1 flex flex-col gap-1 p-4 overflow-y-auto">
          <NavLink to="/" className={LINK_CLASS} end>
            <LayoutDashboard className={IC} /> Inicio
          </NavLink>

          <div className="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 px-3 mt-5 mb-2">
            Emitir
          </div>
          <NavLink to="/nueva-factura" className={LINK_CLASS}>
            <FileText className={IC} /> Factura
          </NavLink>
          <NavLink to="/nueva-boleta" className={LINK_CLASS}>
            <Receipt className={IC} /> Boleta
          </NavLink>
          <NavLink to="/nueva-nc" className={LINK_CLASS}>
            <TrendingDown className={IC} /> Nota de Crédito
          </NavLink>
          <NavLink to="/nueva-nd" className={LINK_CLASS}>
            <TrendingUp className={IC} /> Nota de Débito
          </NavLink>
          <NavLink to="/nueva-guia" className={LINK_CLASS}>
            <Truck className={IC} /> Guía de Remisión
          </NavLink>
          <NavLink to="/resumenes" className={LINK_CLASS}>
            <FileStack className={IC} /> Resumen Diario
          </NavLink>

          <div className="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 px-3 mt-5 mb-2">
            Consultar
          </div>
          <NavLink to="/documentos/facturas" className={LINK_CLASS}>
            <ClipboardList className={IC} /> Facturas
          </NavLink>
          <NavLink to="/documentos/boletas" className={LINK_CLASS}>
            <ClipboardList className={IC} /> Boletas
          </NavLink>
          <NavLink to="/documentos/notas-credito" className={LINK_CLASS}>
            <ClipboardList className={IC} /> Notas Crédito
          </NavLink>
          <NavLink to="/documentos/notas-debito" className={LINK_CLASS}>
            <ClipboardList className={IC} /> Notas Débito
          </NavLink>
          <NavLink to="/documentos/guias-remision" className={LINK_CLASS}>
            <ClipboardList className={IC} /> Guías
          </NavLink>
        </nav>

        <div className="p-4 flex flex-col gap-2">
          <NavLink to="/configuracion" className={LINK_CLASS}>
            <Settings className={IC} /> Configuración
          </NavLink>
          {session && (
            <div className="flex items-center gap-2 px-3 py-2 bg-slate-50 rounded-xl">
              <div className="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-extrabold flex-shrink-0">
                {session.usuario.substring(0, 2).toUpperCase()}
              </div>
              <div className="flex-1 min-w-0">
                <div className="text-xs font-bold text-slate-900 truncate">{session.nombre}</div>
                <div className="text-[10px] text-slate-500 uppercase tracking-wide">@{session.usuario}</div>
              </div>
              <button
                onClick={handleLogout}
                title="Cerrar sesión"
                className="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-md transition-colors flex-shrink-0"
              >
                <LogOut className="w-4 h-4" />
              </button>
            </div>
          )}
        </div>
      </aside>

      {/* Contenido principal */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Topbar móvil */}
        <header className="lg:hidden sticky top-0 z-20 bg-white/80 backdrop-blur-sm px-4 py-3 flex items-center justify-between">
          <button
            onClick={() => setSidebarOpen(true)}
            className="p-2 -ml-2 text-slate-700 hover:bg-slate-100 rounded-lg"
          >
            <Menu className="w-5 h-5" />
          </button>
          <div className="flex items-center gap-2">
            <span className="w-7 h-7 bg-blue-600 rounded-lg flex items-center justify-center text-white">
              <FileDigit className="w-4 h-4" />
            </span>
            <span className="font-extrabold text-sm text-slate-900 tracking-tight">SUNAT Demo</span>
          </div>
          <div className="w-9" />
        </header>

        <main className="flex-1 p-4 md:p-6 lg:p-8 overflow-auto">
          <div className="max-w-6xl mx-auto">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
