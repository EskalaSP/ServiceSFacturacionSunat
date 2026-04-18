import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client.js';
import {
  LayoutDashboard,
  FileText,
  Receipt,
  TrendingDown,
  TrendingUp,
  Truck,
  ClipboardList,
  Loader2,
  XCircle,
  BarChart3,
  PieChart as PieChartIcon,
  Coins,
} from 'lucide-react';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  Legend,
} from 'recharts';

const COLORS_ESTADO = {
  pendiente: '#eab308',
  enviado:   '#3b82f6',
  aceptado:  '#22c55e',
  rechazado: '#ef4444',
  anulado:   '#94a3b8',
};

const COLORS_MONEDA = ['#2563eb', '#f59e0b', '#8b5cf6', '#06b6d4'];

export default function Dashboard() {
  const [indicadores, setIndicadores] = useState(null);
  const [recientes, setRecientes] = useState([]);
  const [ventasMes, setVentasMes] = useState(null);
  const [estadoSunat, setEstadoSunat] = useState(null);
  const [porMoneda, setPorMoneda] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    Promise.all([
      api.panelIndicadores().catch(() => null),
      api.panelDocumentosRecientes().catch(() => null),
      api.panelVentasMensuales().catch(() => null),
      api.panelEstadoSunat().catch(() => null),
      api.panelPorMoneda().catch(() => null),
    ])
      .then(([ind, rec, vm, es, pm]) => {
        if (ind?.data) setIndicadores(ind.data);
        if (rec?.data) setRecientes(rec.data);
        if (vm?.data) setVentasMes(vm.data);
        if (es?.data) setEstadoSunat(es.data);
        if (pm?.data) setPorMoneda(pm.data);
        setLoading(false);
      })
      .catch((e) => {
        setError(e.message);
        setLoading(false);
      });
  }, []);

  const barData = ventasMes?.meses?.map((m) => ({
    mes: m.mes_label.split(' ')[0].slice(0, 3),
    ventas: m.ventas,
  })) || [];

  const estadoData = estadoSunat?.por_estado
    ? Object.entries(estadoSunat.por_estado)
        .filter(([, cnt]) => cnt > 0)
        .map(([estado, cnt]) => ({ name: estado, value: cnt, color: COLORS_ESTADO[estado] || '#94a3b8' }))
    : [];

  const monedaData = porMoneda?.monedas?.map((m, i) => ({
    name: m.moneda,
    value: m.total,
    docs: m.documentos,
    color: COLORS_MONEDA[i % COLORS_MONEDA.length],
  })) || [];

  return (
    <div>
      <h1 className="page-title">
        <LayoutDashboard className="w-7 h-7" />
        Inicio
      </h1>

      {/* Acciones rápidas */}
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-6">
        <QuickAction to="/nueva-factura" Icon={FileText} label="Nueva Factura" color="bg-blue-500" />
        <QuickAction to="/nueva-boleta" Icon={Receipt} label="Nueva Boleta" color="bg-indigo-500" />
        <QuickAction to="/nueva-nc" Icon={TrendingDown} label="Nota Crédito" color="bg-amber-500" />
        <QuickAction to="/nueva-nd" Icon={TrendingUp} label="Nota Débito" color="bg-orange-500" />
        <QuickAction to="/nueva-guia" Icon={Truck} label="Guía Remisión" color="bg-green-500" />
      </div>

      {loading ? (
        <div className="text-center py-8 text-slate-400 flex items-center justify-center gap-2">
          <Loader2 className="w-5 h-5 animate-spin" /> Cargando...
        </div>
      ) : error ? (
        <div className="p-4 bg-red-50 rounded-lg text-red-800">
          <div className="flex items-center gap-2">
            <XCircle className="w-5 h-5" /> {error}
          </div>
          <div className="text-sm mt-1">Verifica tu <Link to="/configuracion" className="underline">configuración</Link>.</div>
        </div>
      ) : (
        <>
          {/* KPIs */}
          {indicadores && (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-6">
              <KpiCard label="Ventas hoy" value={indicadores.hoy?.ventas} cantidad={indicadores.hoy?.documentos} />
              <KpiCard label="Esta semana" value={indicadores.semana?.ventas} cantidad={indicadores.semana?.documentos} />
              <KpiCard label="Mes actual" value={indicadores.mes_actual?.ventas} cantidad={indicadores.mes_actual?.documentos} highlight />
              <KpiCard label="Vs mes anterior" value={indicadores.crecimiento?.vs_mes_anterior} suffix="%" isGrowth />
            </div>
          )}

          {/* Gráfico de barras — 12 meses */}
          <div className="card mb-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="section-title mb-0">
                <BarChart3 className="w-5 h-5 text-blue-600" />
                Ventas últimos 12 meses
              </h2>
              {ventasMes?.total_12_meses !== undefined && (
                <div className="text-right">
                  <div className="text-xs text-slate-500 font-semibold uppercase tracking-wide">Total</div>
                  <div className="text-lg font-extrabold text-slate-900">S/ {ventasMes.total_12_meses.toFixed(2)}</div>
                </div>
              )}
            </div>
            <ResponsiveContainer width="100%" height={280}>
              <BarChart data={barData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="barGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#3b82f6" stopOpacity={1} />
                    <stop offset="100%" stopColor="#3b82f6" stopOpacity={0.4} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
                <XAxis dataKey="mes" tick={{ fontSize: 11, fill: '#64748b', fontWeight: 600 }} tickLine={false} axisLine={false} />
                <YAxis tick={{ fontSize: 11, fill: '#64748b' }} tickLine={false} axisLine={false} tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}K` : v} />
                <Tooltip
                  cursor={{ fill: '#f1f5f9' }}
                  contentStyle={{
                    background: 'white',
                    border: 'none',
                    borderRadius: '0.75rem',
                    boxShadow: '0 4px 20px rgb(15 23 42 / 0.1)',
                    padding: '0.75rem',
                    fontSize: '0.875rem',
                  }}
                  formatter={(v) => [`S/ ${parseFloat(v).toFixed(2)}`, 'Ventas']}
                />
                <Bar dataKey="ventas" fill="url(#barGrad)" radius={[8, 8, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>

          {/* Dos donas lado a lado */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div className="card">
              <h2 className="section-title">
                <PieChartIcon className="w-5 h-5 text-green-600" />
                Estado SUNAT (mes)
              </h2>
              {estadoData.length === 0 ? (
                <div className="text-center py-12 text-slate-400">Sin documentos del periodo</div>
              ) : (
                <>
                  <ResponsiveContainer width="100%" height={220}>
                    <PieChart>
                      <Pie data={estadoData} cx="50%" cy="50%" innerRadius={55} outerRadius={85} paddingAngle={3} dataKey="value">
                        {estadoData.map((entry, i) => (
                          <Cell key={i} fill={entry.color} />
                        ))}
                      </Pie>
                      <Tooltip
                        contentStyle={{ background: 'white', border: 'none', borderRadius: '0.5rem', boxShadow: '0 4px 20px rgb(15 23 42 / 0.1)', fontSize: '0.75rem' }}
                        formatter={(v, n) => [`${v} docs`, n]}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                  <div className="flex flex-wrap gap-2 justify-center mt-2">
                    {estadoData.map((e) => (
                      <div key={e.name} className="flex items-center gap-1.5 text-xs">
                        <span className="w-2.5 h-2.5 rounded-full" style={{ background: e.color }} />
                        <span className="font-semibold text-slate-700 capitalize">{e.name}:</span>
                        <span className="text-slate-500">{e.value}</span>
                      </div>
                    ))}
                  </div>
                  {estadoSunat?.tasa_aceptacion !== undefined && (
                    <div className="text-center mt-3 text-sm">
                      <span className="text-slate-500">Tasa aceptación: </span>
                      <span className="font-extrabold text-green-600">{estadoSunat.tasa_aceptacion}%</span>
                    </div>
                  )}
                </>
              )}
            </div>

            <div className="card">
              <h2 className="section-title">
                <Coins className="w-5 h-5 text-amber-600" />
                Ventas por moneda (mes)
              </h2>
              {monedaData.length === 0 ? (
                <div className="text-center py-12 text-slate-400">Sin ventas del periodo</div>
              ) : (
                <>
                  <ResponsiveContainer width="100%" height={220}>
                    <PieChart>
                      <Pie data={monedaData} cx="50%" cy="50%" innerRadius={55} outerRadius={85} paddingAngle={3} dataKey="value">
                        {monedaData.map((entry, i) => (
                          <Cell key={i} fill={entry.color} />
                        ))}
                      </Pie>
                      <Tooltip
                        contentStyle={{ background: 'white', border: 'none', borderRadius: '0.5rem', boxShadow: '0 4px 20px rgb(15 23 42 / 0.1)', fontSize: '0.75rem' }}
                        formatter={(v, n) => [`${n} ${parseFloat(v).toFixed(2)}`, 'Ventas']}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                  <div className="flex flex-wrap gap-3 justify-center mt-2">
                    {monedaData.map((m) => (
                      <div key={m.name} className="flex items-center gap-1.5 text-xs">
                        <span className="w-2.5 h-2.5 rounded-full" style={{ background: m.color }} />
                        <span className="font-bold text-slate-700">{m.name}:</span>
                        <span className="text-slate-500">{m.value.toFixed(2)} ({m.docs} docs)</span>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </div>
          </div>

          {/* Documentos recientes */}
          {recientes.length > 0 && (
            <div className="card">
              <h2 className="section-title">
                <ClipboardList className="w-5 h-5" />
                Documentos recientes
              </h2>
              <div className="table-wrap">
                <table className="table-std">
                  <thead>
                    <tr>
                      <th>Tipo</th>
                      <th>Número</th>
                      <th>Cliente</th>
                      <th className="text-right">Total</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    {recientes.slice(0, 10).map((d, i) => (
                      <tr key={i}>
                        <td>{d.tipo_descripcion}</td>
                        <td className="font-mono">{d.numero_completo}</td>
                        <td className="truncate max-w-xs">{d.cliente}</td>
                        <td className="text-right">S/ {parseFloat(d.monto_total || 0).toFixed(2)}</td>
                        <td><EstadoBadge estado={d.sunat_status} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

function QuickAction({ to, Icon, label, color }) {
  return (
    <Link
      to={to}
      className={`${color} text-white p-4 rounded-2xl hover:scale-[1.02] transition-transform text-center shadow-sm active:scale-[0.98]`}
    >
      <Icon className="w-7 h-7 md:w-8 md:h-8 mx-auto mb-2" />
      <div className="text-xs md:text-sm font-bold tracking-tight">{label}</div>
    </Link>
  );
}

function KpiCard({ label, value, cantidad, suffix = '', isGrowth = false, highlight = false }) {
  const numValue = value !== undefined && value !== null ? parseFloat(value) : null;
  const formatted = numValue !== null ? numValue.toFixed(2) : '0.00';
  const positive = isGrowth && (numValue ?? 0) > 0;
  const negative = isGrowth && (numValue ?? 0) < 0;
  const prefix = isGrowth ? '' : 'S/ ';

  return (
    <div className={`card ${highlight ? 'ring-2 ring-blue-400 ring-offset-2' : ''}`}>
      <div className="text-[10px] uppercase text-slate-500 font-bold tracking-wider">{label}</div>
      <div className={`text-xl md:text-2xl font-extrabold mt-1 tracking-tight ${positive ? 'text-green-600' : ''} ${negative ? 'text-red-600' : 'text-slate-900'}`}>
        {isGrowth && positive && '+'}{prefix}{formatted}{suffix}
      </div>
      {cantidad !== undefined && (
        <div className="text-xs text-slate-500 mt-1 font-medium">{cantidad ?? 0} docs</div>
      )}
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
  };
  return <span className={`px-2 py-1 rounded text-xs ${colors[estado] || 'bg-slate-100'}`}>{estado}</span>;
}
