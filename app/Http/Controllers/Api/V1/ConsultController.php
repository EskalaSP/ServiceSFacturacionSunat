<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\ConsultCdrAction;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
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
}
