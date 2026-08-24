<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Actions\Documents\CreateCreditNoteAction;
use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Models\Invoice;
use App\Models\Serie;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotaCreditoController extends Controller
{
    public function index(\App\Services\Documents\DocumentoListingService $listing): Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        return Inertia::render('sunat/documentos/index', [
            'titulo' => 'Notas de crédito',
            'subtitulo' => 'Notas de crédito emitidas',
            'nuevo' => ['href' => '/sunat/nota-credito/nueva', 'label' => 'Nueva nota de crédito'],
            'documentos' => $listing->listar($tenant, 'notas-credito'),
        ]);
    }

    private const MOTIVOS = [
        ['codigo' => '01', 'descripcion' => 'Anulación de la operación'],
        ['codigo' => '02', 'descripcion' => 'Anulación por error en el RUC'],
        ['codigo' => '03', 'descripcion' => 'Corrección por error en la descripción'],
        ['codigo' => '04', 'descripcion' => 'Descuento global'],
        ['codigo' => '05', 'descripcion' => 'Descuento por ítem'],
        ['codigo' => '06', 'descripcion' => 'Devolución total'],
        ['codigo' => '07', 'descripcion' => 'Devolución por ítem'],
        ['codigo' => '08', 'descripcion' => 'Bonificación'],
        ['codigo' => '09', 'descripcion' => 'Disminución en el valor'],
        ['codigo' => '10', 'descripcion' => 'Otros conceptos'],
        ['codigo' => '11', 'descripcion' => 'Ajustes de operaciones de exportación'],
        ['codigo' => '13', 'descripcion' => 'Corrección en el monto de percepción'],
    ];

    public function create(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion');
        }

        $docOriginal = null;
        if ($docId = $request->input('doc_id')) {
            $tipoAfectado = $request->input('tipo_doc', '01');
            $model = $tipoAfectado === '03' ? Boleta::class : Invoice::class;
            $found = $model::forTenant($tenant->id)->with('items')->find($docId);

            if ($found) {
                $docOriginal = [
                    'id' => $found->id,
                    'tipo_doc' => $tipoAfectado,
                    'serie' => $found->serie,
                    'correlativo' => $found->correlativo,
                    'numero' => $found->serie.'-'.str_pad((string) $found->correlativo, 8, '0', STR_PAD_LEFT),
                    'cliente' => $found->client_razon_social,
                    'cliente_tipo_doc' => $found->client_tipo_doc,
                    'cliente_num_doc' => $found->client_num_doc,
                    'cliente_direccion' => $found->client_direccion,
                    'total' => (float) $found->mto_imp_venta,
                    'moneda' => $found->tipo_moneda ?? 'PEN',
                    'items' => $found->items->map(fn ($i) => [
                        'descripcion' => $i->descripcion,
                        'cantidad' => $i->cantidad,
                        'precio_unitario' => $i->precio_unitario ?? $i->valor_unitario,
                        'unidad' => $i->unidad_medida ?? 'NIU',
                    ])->toArray(),
                ];
            }
        }

        $series = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '07')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        return Inertia::render('sunat/nota-credito/nueva', [
            'motivos' => self::MOTIVOS,
            'series' => $series,
            'doc_original' => $docOriginal,
            'tenant' => ['ruc' => $tenant->ruc, 'razon_social' => $tenant->razon_social],
        ]);
    }

    public function store(Request $request, CreateCreditNoteAction $action): \Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        \Illuminate\Support\Facades\Gate::authorize('emitir', [$tenant, 'nota_credito']);

        $enviar = $request->boolean('enviar_automatico', true);

        try {
            $nc = $action->execute($tenant, $request->all(), $enviar);
            $numero = $nc->serie.'-'.str_pad((string) $nc->correlativo, 8, '0', STR_PAD_LEFT);

            $msg = $enviar
                ? "Nota de Crédito {$numero} emitida y enviada a SUNAT."
                : "Nota de Crédito {$numero} guardada como borrador.";

            return redirect()->route('sunat.nota-credito.create')
                ->with('success', $msg)
                ->with('emitido', ['tipo' => '07', 'id' => $nc->id, 'numero' => $numero, 'formato' => $request->input('pdf_format', 'a4')]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error: '.$e->getMessage());
        }
    }
}
