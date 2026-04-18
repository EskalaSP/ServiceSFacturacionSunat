import { Search, User, X } from 'lucide-react';

/**
 * Selector compacto de cliente — se comporta como un input.
 * Muestra el cliente seleccionado inline con acciones de buscar/limpiar.
 */
export default function ClientSelector({ cliente, onOpenPicker, onClear, placeholder = 'Seleccionar cliente...' }) {
  return (
    <div
      className={`
        flex items-center gap-2 w-full px-3.5 py-2.5 rounded-xl
        transition-all duration-150 cursor-pointer
        ${cliente ? 'bg-blue-50 hover:bg-blue-100/60' : 'bg-slate-100 hover:bg-slate-200/60'}
      `}
      onClick={onOpenPicker}
    >
      <span className={`flex-shrink-0 ${cliente ? 'text-blue-600' : 'text-slate-400'}`}>
        <User className="w-[18px] h-[18px]" />
      </span>

      {cliente ? (
        <div className="flex-1 min-w-0 flex items-center gap-2 flex-wrap sm:flex-nowrap">
          <span className="badge bg-slate-100 text-slate-700 font-mono text-[11px] flex-shrink-0">
            {cliente.tipo_doc === '6' ? 'RUC' : cliente.tipo_doc === '1' ? 'DNI' : cliente.tipo_doc === '0' ? 'S/D' : cliente.tipo_doc}
            {' '}
            {cliente.num_doc}
          </span>
          <span className="text-sm font-semibold text-slate-900 truncate">
            {cliente.razon_social}
          </span>
        </div>
      ) : (
        <span className="flex-1 text-sm text-slate-400 font-normal">{placeholder}</span>
      )}

      <div className="flex items-center gap-1 flex-shrink-0" onClick={(e) => e.stopPropagation()}>
        {cliente && onClear && (
          <button
            type="button"
            onClick={onClear}
            className="p-1 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-md transition-colors"
            title="Limpiar"
          >
            <X className="w-4 h-4" />
          </button>
        )}
        <button
          type="button"
          onClick={onOpenPicker}
          className="p-1 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
          title="Buscar cliente"
        >
          <Search className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
