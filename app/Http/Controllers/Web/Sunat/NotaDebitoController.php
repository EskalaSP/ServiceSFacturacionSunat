<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateDebitNoteAction;
use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Models\Invoice;
use App\Models\Serie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotaDebitoController extends Controller
{
    private const MOTIVOS = [
        ['codigo' => '01', 'descripcion' => 'Intereses por mora'],
        ['codigo' => '02', 'descripcion' => 'Aumento en el valor'],
        ['codigo' => '03', 'descripcion' => 'Penalidades / otros conceptos'],
    ];

    public function create(Request $request): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $docOriginal = null;
        if ($docId = $request->query('doc_id')) {
            $tipo  = $request->query('tipo', '01');
            $model = $tipo === '03' ? Boleta::class : Invoice::class;
            $doc   = $model::where('tenant_id', $tenant->id)->with('items')->find($docId);

            if ($doc) {
                $docOriginal = [
                    'id'          => $doc->id,
                    'tipo_doc'    => $tipo,
                    'serie'       => $doc->serie,
                    'correlativo' => $doc->correlativo,
                    'numero'      => $doc->serie . '-' . str_pad($doc->correlativo, 8, '0', STR_PAD_LEFT),
                    'cliente'     => $doc->client_razon_social,
                    'total'       => $doc->mto_imp_venta,
                    'moneda'      => $doc->tipo_moneda,
                    'items'       => $doc->items->map(fn ($it) => [
                        'descripcion'     => $it->descripcion,
                        'cantidad'        => $it->cantidad,
                        'precio_unitario' => $it->mto_valor_unitario ?? 0,
                        'unidad'          => $it->unidad_medida ?? 'ZZ',
                    ])->toArray(),
                ];
            }
        }

        $series = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '08')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        return Inertia::render('sunat/nota-debito/nueva', [
            'motivos'      => self::MOTIVOS,
            'series'       => $series,
            'doc_original' => $docOriginal,
            'tenant'       => ['ruc' => $tenant->ruc, 'razon_social' => $tenant->razon_social],
        ]);
    }

    public function store(Request $request, CreateDebitNoteAction $action): \Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->firstOrFail();

        $serieNombre = $request->input('serie', 'FD01');

        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '08', 'serie' => $serieNombre],
            ['correlativo' => 0, 'is_active' => true]
        );

        $data = $request->all();
        // CreateDebitNoteAction espera doc_afectado_correlativo
        if (isset($data['doc_afectado_corr'])) {
            $data['doc_afectado_correlativo'] = $data['doc_afectado_corr'];
        }

        try {
            $doc    = $action->execute($tenant, $data);
            $numero = $doc->serie . '-' . str_pad($doc->correlativo, 8, '0', STR_PAD_LEFT);
            return redirect()->route('sunat.historial')
                ->with('success', "Nota de Débito {$numero} emitida correctamente.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al emitir: ' . $e->getMessage());
        }
    }
}
