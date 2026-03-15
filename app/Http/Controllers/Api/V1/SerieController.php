<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Serie;
use App\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SerieController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $query = Serie::forTenant($tenant->id)->with('sucursal');

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->input('sucursal_id'));
        }

        if ($request->filled('tipo')) {
            $tipoCodigo = Serie::TIPOS[$request->input('tipo')] ?? null;
            if ($tipoCodigo) {
                $query->where('tipo_documento', $tipoCodigo);
            }
        }

        $series = $query->orderBy('tipo_documento')->orderBy('serie')->get();

        return $this->success($series->map(fn ($s) => $this->formatSerie($s)));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');
        $tiposValidos = implode(',', array_keys(Serie::TIPOS));

        // Detectar si es array o un solo objeto
        $items = $request->input('series', []);

        // Si no viene "series", tratar el body como un solo item
        if (empty($items)) {
            $items = [$request->all()];
        }

        $request->validate([
            'series' => 'sometimes|array|min:1',
            'series.*.tipo' => "required|string|in:{$tiposValidos}",
            'series.*.serie' => 'required|string|size:4|regex:/^[A-Z][A-Z0-9]{3}$/',
            'series.*.sucursal_id' => 'required|integer|exists:sucursales,id',
            'series.*.correlativo_inicial' => 'sometimes|integer|min:0',
            // Para un solo item sin wrapper "series"
            'tipo' => "sometimes|string|in:{$tiposValidos}",
            'serie' => 'sometimes|string|size:4|regex:/^[A-Z][A-Z0-9]{3}$/',
            'sucursal_id' => 'sometimes|integer|exists:sucursales,id',
            'correlativo_inicial' => 'sometimes|integer|min:0',
        ], [
            'series.*.tipo.in' => "El tipo debe ser uno de: {$tiposValidos}",
            'series.*.serie.regex' => 'La serie debe ser 4 caracteres (ej: F001, B001).',
            'series.*.sucursal_id.required' => 'Debe indicar la sucursal.',
            'tipo.in' => "El tipo debe ser uno de: {$tiposValidos}",
            'serie.regex' => 'La serie debe ser 4 caracteres (ej: F001, B001).',
        ]);

        // Precargar sucursales del tenant para validar
        $sucursalIds = collect($items)->pluck('sucursal_id')->unique()->filter();
        $sucursales = Sucursal::forTenant($tenant->id)
            ->whereIn('id', $sucursalIds)
            ->get()
            ->keyBy('id');

        $creadas = [];
        $errores = [];

        DB::transaction(function () use ($items, $tenant, $sucursales, &$creadas, &$errores) {
            foreach ($items as $index => $item) {
                $tipo = $item['tipo'] ?? null;
                $serieValue = strtoupper($item['serie'] ?? '');
                $sucursalId = $item['sucursal_id'] ?? null;
                $correlativoInicial = $item['correlativo_inicial'] ?? 0;

                // Validar tipo
                if (! $tipo || ! isset(Serie::TIPOS[$tipo])) {
                    $errores[] = "#{$index}: tipo '{$tipo}' no válido.";
                    continue;
                }

                $tipoCodigo = Serie::TIPOS[$tipo];

                // Validar sucursal
                $sucursal = $sucursales->get($sucursalId);
                if (! $sucursal) {
                    $errores[] = "#{$index} ({$serieValue}): sucursal_id {$sucursalId} no encontrada.";
                    continue;
                }

                if (! $sucursal->is_active) {
                    $errores[] = "#{$index} ({$serieValue}): sucursal '{$sucursal->nombre}' está desactivada.";
                    continue;
                }

                // Validar prefijo
                $prefijosValidos = Serie::PREFIJOS[$tipoCodigo] ?? [];
                $primerLetra = substr($serieValue, 0, 1);

                if (! empty($prefijosValidos) && ! in_array($primerLetra, $prefijosValidos)) {
                    $prefijos = implode(', ', array_map(fn ($p) => "{$p}XXX", $prefijosValidos));
                    $errores[] = "#{$index} ({$serieValue}): para {$tipo} la serie debe empezar con: {$prefijos}.";
                    continue;
                }

                // Verificar duplicado
                $exists = Serie::where('tenant_id', $tenant->id)
                    ->where('tipo_documento', $tipoCodigo)
                    ->where('serie', $serieValue)
                    ->exists();

                if ($exists) {
                    $errores[] = "#{$index} ({$serieValue}): ya existe para {$tipo}.";
                    continue;
                }

                $serie = Serie::create([
                    'tenant_id' => $tenant->id,
                    'tipo_documento' => $tipoCodigo,
                    'serie' => $serieValue,
                    'correlativo' => $correlativoInicial,
                    'sucursal_id' => $sucursal->id,
                    'sucursal_nombre' => $sucursal->nombre,
                    'is_active' => true,
                ]);

                $serie->setRelation('sucursal', $sucursal);
                $creadas[] = $this->formatSerie($serie);
            }
        });

        if (! empty($errores) && empty($creadas)) {
            return $this->error('No se pudo crear ninguna serie.', 422, $errores);
        }

        $response = ['creadas' => $creadas];

        if (! empty($errores)) {
            $response['errores'] = $errores;
        }

        return $this->created($response);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $serie = Serie::forTenant($tenant->id)->with('sucursal')->findOrFail($id);

        return $this->success($this->formatSerie($serie));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'sometimes|boolean',
            'correlativo' => 'sometimes|integer|min:0',
            'sucursal_id' => 'sometimes|integer|exists:sucursales,id',
        ]);

        $tenant = $request->get('tenant');
        $serie = Serie::forTenant($tenant->id)->findOrFail($id);

        if ($request->filled('sucursal_id')) {
            $sucursal = Sucursal::forTenant($tenant->id)->find($request->input('sucursal_id'));
            if (! $sucursal) {
                return $this->error('La sucursal no pertenece a su empresa.', 403);
            }
            $serie->update([
                'sucursal_id' => $sucursal->id,
                'sucursal_nombre' => $sucursal->nombre,
            ]);
        }

        $serie->update($request->only(['is_active', 'correlativo']));

        return $this->success($this->formatSerie($serie->load('sucursal')), 'Serie actualizada.');
    }

    private function formatSerie(Serie $serie): array
    {
        return [
            'id' => $serie->id,
            'tipo' => Serie::TIPOS_NOMBRE[$serie->tipo_documento] ?? $serie->tipo_documento,
            'tipo_documento' => $serie->tipo_documento,
            'serie' => $serie->serie,
            'correlativo' => $serie->correlativo,
            'siguiente_numero' => $serie->serie.'-'.($serie->correlativo + 1),
            'sucursal' => $serie->sucursal ? [
                'id' => $serie->sucursal->id,
                'nombre' => $serie->sucursal->nombre,
                'cod_local' => $serie->sucursal->cod_local,
            ] : null,
            'is_active' => $serie->is_active,
        ];
    }
}
