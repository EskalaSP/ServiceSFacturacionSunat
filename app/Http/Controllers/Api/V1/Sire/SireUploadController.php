<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Exceptions\SireException;
use App\Sire\Services\Preliminar\NoDomiciliadosService;
use App\Sire\Services\Propuesta\ComplementarPropuestaService;
use App\Sire\Services\Propuesta\ReplacePropuestaService;
use App\Sire\Support\PeriodoTributario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints para subir archivos a SIRE (servicios TUS: 5.3, 5.5, 5.6).
 *
 * Convención de entrada:
 *   - multipart/form-data con campo `archivo` (el .zip preparado), o
 *   - JSON con campo `txt` (contenido del TXT en texto plano — se empaqueta aquí)
 *
 * Devuelve 202 + num_ticket.
 */
class SireUploadController extends Controller
{
    use ApiResponse;

    /**
     * POST /v1/sire/rce/{periodo}/reemplazar-propuesta
     * Servicio 5.3
     */
    public function reemplazarPropuesta(
        Request $request,
        string $periodo,
        ReplacePropuestaService $service,
    ): JsonResponse {
        return $this->handleUpload($request, $periodo, $service, 'Propuesta reemplazada. Siga el ticket.');
    }

    /**
     * POST /v1/sire/rce/{periodo}/no-domiciliados
     * Servicio 5.5
     */
    public function noDomiciliados(
        Request $request,
        string $periodo,
        NoDomiciliadosService $service,
    ): JsonResponse {
        return $this->handleUpload($request, $periodo, $service, 'No domiciliados cargados. Siga el ticket.');
    }

    /**
     * POST /v1/sire/rce/{periodo}/complementar-propuesta
     * Servicio 5.6
     */
    public function complementarPropuesta(
        Request $request,
        string $periodo,
        ComplementarPropuestaService $service,
    ): JsonResponse {
        return $this->handleUpload($request, $periodo, $service, 'Propuesta complementada. Siga el ticket.');
    }

    /**
     * Flujo común de validación + dispatch.
     */
    private function handleUpload(
        Request $request,
        string $periodo,
        \App\Sire\Services\Upload\BaseUploadService $service,
        string $successMessage,
    ): JsonResponse {
        try {
            $per = PeriodoTributario::fromString($periodo);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $request->validate([
            'archivo'   => 'required_without:txt|file|mimes:zip|max:6291456', // 6 GB = 6*1024*1024 KB
            'txt'       => 'required_without:archivo|string',
            'secuencia' => 'sometimes|integer|min:1|max:99999999',
        ]);

        $tenant    = $request->get('tenant');
        $secuencia = (int) ($request->input('secuencia', 1));

        try {
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $built = (object) [
                    'zipPath' => $file->getRealPath(),
                    'zipName' => $file->getClientOriginalName(),
                    'txtName' => null,
                    'size'    => $file->getSize(),
                    'sha256'  => hash_file('sha256', $file->getRealPath()),
                ];
                $ticket = $service->uploadZipFile($tenant, $per, $built);
            } else {
                $ticket = $service->uploadTxt($tenant, $per, $request->input('txt'), $secuencia);
            }

            return $this->success([
                'num_ticket'     => $ticket->num_ticket,
                'per_tributario' => $ticket->per_tributario,
                'estado'         => $ticket->cod_estado_proceso,
                'archivo'        => $ticket->nom_archivo_importacion,
                'mensaje'        => $successMessage,
            ], 'Upload aceptado', 202);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
