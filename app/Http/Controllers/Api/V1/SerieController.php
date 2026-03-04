<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Serie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SerieController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->get('tenant');

        $series = Serie::forTenant($tenant->id)->orderBy('tipo_documento')->orderBy('serie')->get();

        return $this->success($series->map(fn ($s) => [
            'id' => $s->id,
            'tipo_documento' => $s->tipo_documento,
            'serie' => $s->serie,
            'correlativo' => $s->correlativo,
            'is_active' => $s->is_active,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tipo_documento' => 'required|string|in:01,03,07,08,09,20,40,RC,RA',
            'serie' => 'required|string|size:4',
        ]);

        $tenant = $request->get('tenant');

        $exists = Serie::where('tenant_id', $tenant->id)
            ->where('tipo_documento', $request->input('tipo_documento'))
            ->where('serie', $request->input('serie'))
            ->exists();

        if ($exists) {
            return $this->error('La serie ya existe para este tipo de documento.', 409);
        }

        $serie = Serie::create([
            'tenant_id' => $tenant->id,
            'tipo_documento' => $request->input('tipo_documento'),
            'serie' => $request->input('serie'),
            'correlativo' => 0,
            'is_active' => true,
        ]);

        return $this->created([
            'id' => $serie->id,
            'tipo_documento' => $serie->tipo_documento,
            'serie' => $serie->serie,
            'correlativo' => $serie->correlativo,
            'is_active' => $serie->is_active,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->get('tenant');
        $serie = Serie::forTenant($tenant->id)->findOrFail($id);

        return $this->success([
            'id' => $serie->id,
            'tipo_documento' => $serie->tipo_documento,
            'serie' => $serie->serie,
            'correlativo' => $serie->correlativo,
            'is_active' => $serie->is_active,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'sometimes|boolean',
            'correlativo' => 'sometimes|integer|min:0',
        ]);

        $tenant = $request->get('tenant');
        $serie = Serie::forTenant($tenant->id)->findOrFail($id);
        $serie->update($request->only(['is_active', 'correlativo']));

        return $this->success([
            'id' => $serie->id,
            'tipo_documento' => $serie->tipo_documento,
            'serie' => $serie->serie,
            'correlativo' => $serie->correlativo,
            'is_active' => $serie->is_active,
        ], 'Serie actualizada.');
    }
}
