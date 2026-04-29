<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HistorialController extends Controller
{
    public function index(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion');
        }

        $tipo    = $request->input('tipo', 'todos');
        $estado  = $request->input('estado');
        $desde   = $request->input('desde');
        $hasta   = $request->input('hasta');
        $cliente = $request->input('cliente');

        $applyFilters = function ($query) use ($estado, $desde, $hasta, $cliente) {
            if ($estado) {
                $query->where('sunat_status', $estado);
            }
            if ($desde) {
                $query->whereDate('fecha_emision', '>=', $desde);
            }
            if ($hasta) {
                $query->whereDate('fecha_emision', '<=', $hasta);
            }
            if ($cliente) {
                $query->where('client_razon_social', 'like', "%{$cliente}%");
            }

            return $query->orderByDesc('fecha_emision');
        };

        if ($tipo === 'facturas') {
            $rows = $applyFilters(Invoice::forTenant($tenant->id))
                ->paginate(20)
                ->through(fn ($d) => $this->mapDoc($d, '01'));
        } elseif ($tipo === 'boletas') {
            $rows = $applyFilters(Boleta::forTenant($tenant->id))
                ->paginate(20)
                ->through(fn ($d) => $this->mapDoc($d, '03'));
        } else {
            $facturas = $applyFilters(Invoice::forTenant($tenant->id))
                ->limit(150)->get()->map(fn ($d) => $this->mapDoc($d, '01'));

            $boletas = $applyFilters(Boleta::forTenant($tenant->id))
                ->limit(150)->get()->map(fn ($d) => $this->mapDoc($d, '03'));

            $nc = $applyFilters(CreditNote::forTenant($tenant->id))
                ->limit(50)->get()->map(fn ($d) => $this->mapDoc($d, '07'));

            $merged = $facturas->concat($boletas)->concat($nc)
                ->sortByDesc('fecha')->take(100)->values();

            $rows = ['data' => $merged, 'total' => $merged->count()];
        }

        return Inertia::render('sunat/historial', [
            'documentos' => $rows,
            'filtros'    => compact('tipo', 'estado', 'desde', 'hasta', 'cliente'),
            'tenant'     => ['environment' => $tenant->environment ?? 'beta'],
        ]);
    }

    public function pdf(string $tipo, int $id): mixed
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        $model  = match ($tipo) {
            '03' => Boleta::class,
            '07' => CreditNote::class,
            default => Invoice::class,
        };
        $doc = $model::forTenant($tenant->id)->findOrFail($id);

        if (! empty($doc->pdf_path) && Storage::exists($doc->pdf_path)) {
            return Storage::response($doc->pdf_path, "{$doc->serie}-{$doc->correlativo}.pdf");
        }

        abort(404, 'PDF no disponible aún.');
    }

    public function xml(string $tipo, int $id): mixed
    {
        $tenant = auth()->user()->tenants()->firstOrFail();
        $model  = match ($tipo) {
            '03' => Boleta::class,
            '07' => CreditNote::class,
            default => Invoice::class,
        };
        $doc = $model::forTenant($tenant->id)->findOrFail($id);

        if (! empty($doc->xml_path) && Storage::exists($doc->xml_path)) {
            return Storage::response($doc->xml_path, "{$doc->serie}-{$doc->correlativo}.xml");
        }

        abort(404, 'XML no disponible aún.');
    }

    private function mapDoc(object $doc, string $tipoDoc): array
    {
        return [
            'id'         => $doc->id,
            'tipo_doc'   => $tipoDoc,
            'serie'      => $doc->serie,
            'correlativo' => $doc->correlativo,
            'numero'     => $doc->serie . '-' . str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT),
            'cliente'    => $doc->client_razon_social,
            'fecha'      => $doc->fecha_emision,
            'total'      => (float) $doc->mto_imp_venta,
            'moneda'     => $doc->tipo_moneda ?? 'PEN',
            'estado'     => $doc->sunat_status ?? 'pendiente',
            'tiene_pdf'  => ! empty($doc->pdf_path),
            'tiene_xml'  => ! empty($doc->xml_path),
        ];
    }
}
