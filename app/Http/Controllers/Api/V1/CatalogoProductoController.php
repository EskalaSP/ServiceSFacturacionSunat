<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UnspscCode;
use App\Models\UnspscTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador del Código de Producto SUNAT (UNSPSC v14, Catálogo N.° 25).
 *
 * Replica —y mejora— la búsqueda del Excel oficial (CCNU):
 *  - Búsqueda por texto o por código (lo más cómodo para el cliente).
 *  - Drill-down jerárquico: Segmento → Familia → Clase → Producto (como el Excel).
 *  - Validación/detalle de un código puntual.
 */
class CatalogoProductoController extends Controller
{
    /** GET /catalogos/producto-sunat?q={texto|codigo}&per_page=&page= */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'estado' => 'error',
                'mensaje' => 'El parámetro "q" debe tener al menos 2 caracteres.',
            ], 422);
        }

        $query = UnspscCode::query();

        if (ctype_digit($q)) {
            // Búsqueda por código: prefijo (2–8 dígitos)
            $query->where('codigo', 'like', $q . '%')->orderBy('codigo');
        } else {
            // Búsqueda por texto: todas las palabras deben aparecer (AND)
            $words = preg_split('/\s+/', $q);
            foreach ($words as $word) {
                $query->where('descripcion', 'like', '%' . $word . '%');
            }
            // Relevancia: primero los que empiezan con la primera palabra
            $query->orderByRaw('CASE WHEN descripcion LIKE ? THEN 0 ELSE 1 END', [$words[0] . '%'])
                ->orderByRaw('CHAR_LENGTH(descripcion)')
                ->orderBy('codigo');
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'estado' => 'exito',
            'total' => $page->total(),
            'pagina' => $page->currentPage(),
            'por_pagina' => $page->perPage(),
            'datos' => collect($page->items())->map(fn ($c) => [
                'codigo' => $c->codigo,
                'descripcion' => $c->descripcion,
                'clase' => $c->clase,
            ])->all(),
        ]);
    }

    /** GET /catalogos/producto-sunat/{codigo} — valida y devuelve el detalle con su jerarquía */
    public function show(string $codigo): JsonResponse
    {
        if (! preg_match('/^\d{8}$/', $codigo)) {
            return response()->json([
                'estado' => 'error',
                'valido' => false,
                'mensaje' => 'El código debe tener 8 dígitos numéricos.',
            ], 422);
        }

        $producto = UnspscCode::find($codigo);

        if (! $producto) {
            return response()->json([
                'estado' => 'error',
                'valido' => false,
                'mensaje' => "El código {$codigo} no existe en el catálogo UNSPSC v14.",
            ], 404);
        }

        return response()->json([
            'estado' => 'exito',
            'valido' => true,
            'datos' => [
                'codigo' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'jerarquia' => $this->jerarquia($codigo),
            ],
        ]);
    }

    /** GET /catalogos/producto-sunat/segmentos */
    public function segmentos(): JsonResponse
    {
        return $this->nivelResponse(
            UnspscTaxonomy::where('nivel', 'segmento')->orderBy('codigo')->get()
        );
    }

    /** GET /catalogos/producto-sunat/familias?segmento=84 */
    public function familias(Request $request): JsonResponse
    {
        $segmento = (string) $request->query('segmento', '');
        if (! preg_match('/^\d{2}$/', $segmento)) {
            return $this->paramError('segmento', '2 dígitos');
        }

        return $this->nivelResponse(
            UnspscTaxonomy::where('nivel', 'familia')->where('parent', $segmento)->orderBy('codigo')->get()
        );
    }

    /** GET /catalogos/producto-sunat/clases?familia=8412 */
    public function clases(Request $request): JsonResponse
    {
        $familia = (string) $request->query('familia', '');
        if (! preg_match('/^\d{4}$/', $familia)) {
            return $this->paramError('familia', '4 dígitos');
        }

        return $this->nivelResponse(
            UnspscTaxonomy::where('nivel', 'clase')->where('parent', $familia)->orderBy('codigo')->get()
        );
    }

    /** GET /catalogos/producto-sunat/productos?clase=841218 — como el "Buscar" del Excel */
    public function productos(Request $request): JsonResponse
    {
        $clase = (string) $request->query('clase', '');
        if (! preg_match('/^\d{6}$/', $clase)) {
            return $this->paramError('clase', '6 dígitos');
        }

        $items = UnspscCode::where('clase', $clase)
            ->where('codigo', 'not like', '%00') // solo productos, no el nivel clase
            ->orderBy('codigo')
            ->get();

        return response()->json([
            'estado' => 'exito',
            'total' => $items->count(),
            'datos' => $items->map(fn ($c) => [
                'codigo' => $c->codigo,
                'descripcion' => $c->descripcion,
            ])->all(),
        ]);
    }

    /** Jerarquía (segmento/familia/clase) de un código de 8 dígitos. */
    private function jerarquia(string $codigo): array
    {
        $niveles = [
            'segmento' => substr($codigo, 0, 2),
            'familia' => substr($codigo, 0, 4),
            'clase' => substr($codigo, 0, 6),
        ];

        $rows = UnspscTaxonomy::whereIn('codigo', array_values($niveles))->get()->keyBy(fn ($r) => $r->nivel . ':' . $r->codigo);

        $out = [];
        foreach ($niveles as $nivel => $cod) {
            $row = $rows->get($nivel . ':' . $cod);
            $out[$nivel] = ['codigo' => $cod, 'nombre' => $row?->nombre];
        }

        return $out;
    }

    private function nivelResponse($items): JsonResponse
    {
        return response()->json([
            'estado' => 'exito',
            'total' => $items->count(),
            'datos' => $items->map(fn ($r) => [
                'codigo' => $r->codigo,
                'nombre' => $r->nombre,
            ])->all(),
        ]);
    }

    private function paramError(string $param, string $formato): JsonResponse
    {
        return response()->json([
            'estado' => 'error',
            'mensaje' => "El parámetro \"{$param}\" es obligatorio y debe tener {$formato}.",
        ], 422);
    }
}
