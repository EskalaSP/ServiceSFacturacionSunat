<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\ConsultCdrAction;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Client;
use App\Services\DocumentLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultController extends Controller
{
    use ApiResponse;

    public function cdrStatus(Request $request, ConsultCdrAction $action): JsonResponse
    {
        $request->validate([
            'tipo_documento' => 'required|string|in:01,03,07,08',
            'serie' => 'required|string|size:4',
            'correlativo' => 'required|integer|min:1',
        ]);

        $tenant = $request->get('tenant');

        try {
            $result = $action->execute(
                $tenant,
                $request->input('tipo_documento'),
                $request->input('serie'),
                $request->integer('correlativo')
            );

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error('Error al consultar CDR: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Buscar cliente por RUC/DNI.
     * 1. Busca en la base de datos local del tenant.
     * 2. Si no existe, consulta API externa (SUNAT/RENIEC).
     */
    public function lookupDocument(Request $request, DocumentLookupService $lookup): JsonResponse
    {
        $request->validate([
            'tipo' => 'required|string|in:1,4,6,7,0',
            'numero' => 'required|string|min:8|max:15',
        ]);

        $tenant = $request->get('tenant');
        $tipo = $request->input('tipo');
        $numero = $request->input('numero');

        // 1. Buscar en la BD local (usa índice compuesto tenant_id + tipo_documento + numero_documento)
        $tipoDocMap = ['1' => '1', '6' => '6', '4' => '4', '7' => '7'];
        $tipoDoc = $tipoDocMap[$tipo] ?? $tipo;
        $client = Client::where('tenant_id', $tenant->id)
            ->where('tipo_documento', $tipoDoc)
            ->where('numero_documento', $numero)
            ->first();

        if ($client) {
            return $this->success([
                'tipo_doc' => $client->tipo_documento,
                'num_doc' => $client->numero_documento,
                'razon_social' => $client->razon_social,
                'direccion' => $client->direccion ?? '',
                'fuente' => 'local',
            ]);
        }

        // 2. Consultar API externa (solo RUC y DNI)
        if (in_array($tipo, ['1', '6'])) {
            $result = $lookup->lookup($tipo, $numero);

            if ($result) {
                return $this->success($result);
            }
        }

        return $this->error('No se encontró información para el documento ingresado', 404);
    }
}
