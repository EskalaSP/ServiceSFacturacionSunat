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
    public function index(\App\Services\Documents\DocumentoListingService $listing): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        return Inertia::render('sunat/documentos/index', [
            'titulo' => 'Notas de débito',
            'subtitulo' => 'Notas de débito emitidas',
            'nuevo' => ['href' => '/sunat/nota-debito/nueva', 'label' => 'Nueva nota de débito'],
            'documentos' => $listing->listar($tenant, 'notas-debito'),
        ]);
    }

    private const MOTIVOS = [
        ['codigo' => '01', 'descripcion' => 'Intereses por mora'],
        ['codigo' => '02', 'descripcion' => 'Aumento en el valor'],
        ['codigo' => '03', 'descripcion' => 'Penalidades / otros conceptos'],
    ];

    public function create(Request $request): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();

        if (! $tenant || ! $tenant->sol_user) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        $docOriginal = null;
        if ($docId = $request->query('doc_id')) {
            $tipo = $request->query('tipo', '01');
            $model = $tipo === '03' ? Boleta::class : Invoice::class;
            $doc = $model::where('tenant_id', $tenant->id)->with('items')->find($docId);

            if ($doc) {
                $docOriginal = [
                    'id' => $doc->id,
                    'tipo_doc' => $tipo,
                    'serie' => $doc->serie,
                    'correlativo' => $doc->correlativo,
                    'numero' => $doc->serie.'-'.str_pad($doc->correlativo, 8, '0', STR_PAD_LEFT),
                    'cliente' => $doc->client_razon_social,
                    'cliente_tipo_doc' => $doc->client_tipo_doc,
                    'cliente_num_doc' => $doc->client_num_doc,
                    'cliente_direccion' => $doc->client_direccion,
                    'total' => $doc->mto_imp_venta,
                    'moneda' => $doc->tipo_moneda,
                    'items' => $doc->items->map(fn ($it) => [
                        'descripcion' => $it->descripcion,
                        'cantidad' => $it->cantidad,
                        'precio_unitario' => $it->mto_valor_unitario ?? 0,
                        'unidad' => $it->unidad_medida ?? 'ZZ',
                    ])->toArray(),
                ];
            }
        }

        $series = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', '08')
            ->where('is_active', true)
            ->get(['id', 'serie', 'tipo_documento', 'correlativo']);

        return Inertia::render('sunat/nota-debito/nueva', [
            'motivos' => self::MOTIVOS,
            'series' => $series,
            'doc_original' => $docOriginal,
            'tenant' => ['ruc' => $tenant->ruc, 'razon_social' => $tenant->razon_social],
        ]);
    }

    public function store(Request $request, CreateDebitNoteAction $action): \Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        \Illuminate\Support\Facades\Gate::authorize('emitir', [$tenant, 'nota_debito']);

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

        $enviar = $request->boolean('enviar_automatico', true);

        try {
            $doc = $action->execute($tenant, $data, $enviar);
            $numero = $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT);

            $msg = $enviar
                ? "Nota de Débito {$numero} emitida y enviada a SUNAT."
                : "Nota de Débito {$numero} guardada como borrador.";

            return redirect()->route('sunat.nota-debito.create')
                ->with('success', $msg)
                ->with('emitido', ['tipo' => '08', 'id' => $doc->id, 'numero' => $numero, 'formato' => $request->input('pdf_format', 'a4')]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Error al emitir: '.$e->getMessage());
        }
    }
}
