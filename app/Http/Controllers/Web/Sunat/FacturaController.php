<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateBoletaAction;
use App\Actions\Documents\CreateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\Serie;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FacturaController extends Controller
{
    public function create(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $seriesFactura = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '01')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        $seriesBoleta = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '03')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        $clientes = Client::where('tenant_id', $tenant->id)
            ->orderBy('razon_social')
            ->limit(50)
            ->get(['id', 'tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'email']);

        // Pre-fill desde cotización si viene el parámetro
        $cotizacion = null;
        if ($cotId = $request->input('cotizacion_id')) {
            $cot = Quotation::where('tenant_id', $tenant->id)->with('items')->find($cotId);
            if ($cot) {
                $cotizacion = [
                    'numero'   => $cot->numero,
                    'moneda'   => $cot->tipo_moneda,
                    'cliente'  => [
                        'tipo_documento'   => $cot->client_tipo_doc,
                        'numero_documento' => $cot->client_num_doc,
                        'razon_social'     => $cot->client_razon_social,
                        'direccion'        => $cot->client_direccion ?? '',
                    ],
                    'items'    => $cot->items->map(fn ($it) => [
                        'descripcion'     => $it->descripcion,
                        'unidad'          => $it->unidad_medida ?? 'ZZ',
                        'cantidad'        => (float) $it->cantidad,
                        'precio_unitario' => (float) ($it->mto_valor_unitario ?? 0),
                        'tip_afe_igv'     => $it->tip_afe_igv ?? '10',
                    ])->toArray(),
                    'observacion' => $cot->observacion,
                ];
            }
        }

        return Inertia::render('sunat/facturas/nueva', [
            'tenant'         => [
                'ruc'          => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'environment'  => $tenant->environment ?? 'beta',
            ],
            'series_factura' => $seriesFactura,
            'series_boleta'  => $seriesBoleta,
            'clientes'       => $clientes,
            'tipo_inicial'   => $request->input('tipo', 'factura'),
            'cotizacion'     => $cotizacion,
        ]);
    }

    public function store(
        Request $request,
        CreateInvoiceAction $createInvoice,
        CreateBoletaAction $createBoleta,
    ): \Illuminate\Http\RedirectResponse {
        $tenant = auth()->user()->tenants()->firstOrFail();

        $tipo = $request->input('tipo_documento', '01');
        $enviar = $request->boolean('enviar_automatico', true);

        try {
            if ($tipo === '03') {
                $doc = $createBoleta->execute($tenant, $request->all(), false, $enviar);
                $label = 'Boleta';
            } else {
                $doc = $createInvoice->execute($tenant, $request->all(), $enviar);
                $label = 'Factura';
            }

            $numero = $doc->serie . '-' . str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT);
            $msg = $enviar
                ? "{$label} {$numero} emitida y enviada a SUNAT."
                : "{$label} {$numero} guardada como borrador.";

            return redirect()->route('sunat.historial')->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al emitir: ' . $e->getMessage());
        }
    }
}
