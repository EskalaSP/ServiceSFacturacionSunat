<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SunatCpeConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CpeConsultaController extends Controller
{
    use ApiResponse;

    public function consultar(Request $request, SunatCpeConsultaService $service): JsonResponse
    {
        $validated = $request->validate([
            'ruc_emisor' => 'nullable|string|size:11',
            'tipo_doc' => 'required|string|in:01,03,04,07,08,R1,R7',
            'serie' => 'required|string|max:4',
            'correlativo' => 'required|integer|min:1',
            'fecha_emision' => ['required', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'monto' => 'required|numeric|min:0',
        ]);

        $tenant = $request->get('tenant');

        $validated['ruc_emisor'] = $validated['ruc_emisor'] ?? $tenant->ruc;

        try {
            $result = $service->consultar($tenant, $validated);

            return $this->success(array_merge($result, [
                'consultado_por_ruc' => $tenant->ruc,
                'ruc_emisor' => $validated['ruc_emisor'],
                'tipo_doc' => $validated['tipo_doc'],
                'serie' => $validated['serie'],
                'correlativo' => $validated['correlativo'],
            ]));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            if (str_contains($msg, 'unauthorized_client') || str_contains($msg, 'cliente no autorizado')) {
                return $this->error(
                    'SUNAT rechazó las credenciales (client_id/client_secret). Verifica que las generaste desde Clave SOL → Empresas → Comprobantes de pago → Consulta Integrada CPE → API. No hay entorno beta, solo producción.',
                    401
                );
            }

            if (str_contains($msg, 'invalid_client')) {
                return $this->error('El client_id o client_secret es inválido. Regenera las credenciales en Clave SOL.', 401);
            }

            return $this->error('Error al consultar CPE en SUNAT: ' . $msg, 500);
        }
    }
}
