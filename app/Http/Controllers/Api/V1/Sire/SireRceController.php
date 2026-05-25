<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Enums\CodTipoArchivo;
use App\Sire\Enums\CodTipoResumen;
use App\Sire\Exceptions\SireException;
use App\Sire\Services\Constancia\ConstanciaService;
use App\Sire\Services\Preliminar\RegisterPreliminarService;
use App\Sire\Services\Propuesta\AcceptPropuestaService;
use App\Sire\Services\Propuesta\DownloadPropuestaService;
use App\Sire\Services\Resumen\ResumenService;
use App\Sire\Support\PeriodoTributario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orquesta los endpoints RCE principales:
 *  - Descargar propuesta (5.34)
 *  - Aceptar propuesta (5.2)
 *  - Registrar preliminar (5.4)
 *  - Descargar resumen (5.35)
 *  - Descargar constancia (5.49)
 *
 * Todos los endpoints async devuelven 202 + num_ticket.
 * Los síncronos devuelven el archivo directamente.
 */
class SireRceController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/sire/rce/{periodo}/propuesta
     * Solicita la generación de la propuesta. Devuelve 202 + ticket.
     */
    public function propuesta(
        Request $request,
        string $periodo,
        DownloadPropuestaService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'formato'            => 'sometimes|in:txt,csv,excel',
            'fec_emision_ini'    => 'sometimes|date_format:Y-m-d',
            'fec_emision_fin'    => 'sometimes|date_format:Y-m-d',
            'cod_tipo_cdp'       => 'sometimes|string|size:2',
            'num_serie_cdp'      => 'sometimes|string',
            'num_cdp'            => 'sometimes|string',
            'num_doc_adquiriente'=> 'sometimes|string',
            'mto_desde'          => 'sometimes|numeric',
            'mto_hasta'          => 'sometimes|numeric',
        ]);

        try {
            $per = $this->parsePeriodo($periodo);
            $formato = $this->parseFormato($validated['formato'] ?? 'txt');

            $ticket = $service->solicitar($request->get('tenant'), $per, $formato, $validated);

            return $this->success([
                'num_ticket'    => $ticket->num_ticket,
                'per_tributario'=> $ticket->per_tributario,
                'estado'        => $ticket->cod_estado_proceso,
                'mensaje'       => 'Propuesta solicitada. Consulta el ticket para obtener el archivo.',
            ], 'Solicitud aceptada', 202);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /v1/sire/rce/{periodo}/aceptar-propuesta
     */
    public function aceptarPropuesta(
        Request $request,
        string $periodo,
        AcceptPropuestaService $service,
    ): JsonResponse {
        try {
            $per = $this->parsePeriodo($periodo);
            $ticket = $service->aceptar($request->get('tenant'), $per);

            return $this->success([
                'num_ticket' => $ticket->num_ticket,
                'per_tributario' => $ticket->per_tributario,
                'estado' => $ticket->cod_estado_proceso,
                'mensaje' => 'Propuesta aceptada. El sistema cambiará al estado "Preliminar registrado" cuando el ticket termine.',
            ], 'Solicitud aceptada', 202);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /v1/sire/rce/{periodo}/registrar-preliminar
     * Síncrono — devuelve confirmación directa.
     */
    public function registrarPreliminar(
        Request $request,
        string $periodo,
        RegisterPreliminarService $service,
    ): JsonResponse {
        try {
            $per = $this->parsePeriodo($periodo);
            $result = $service->registrar($request->get('tenant'), $per);

            return $this->success([
                'exitoso' => $result['exitoso'],
                'per_tributario' => $per->toString(),
                'respuesta_sunat' => $result['respuesta'],
            ]);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * GET /v1/sire/rce/{periodo}/resumen
     * Descarga directa (binario).
     */
    public function resumen(
        Request $request,
        string $periodo,
        ResumenService $service,
    ): StreamedResponse|JsonResponse {
        $validated = $request->validate([
            'tipo' => 'sometimes|in:propuesta,preliminar,registro,preliminar_registrado,ajustes_posteriores,no_domiciliados,no_incluidos_excluidos',
            'formato' => 'sometimes|in:txt,csv,excel',
        ]);

        try {
            $per = $this->parsePeriodo($periodo);
            $tipo = $this->parseTipoResumen($validated['tipo'] ?? 'propuesta');
            $formato = $this->parseFormato($validated['formato'] ?? 'txt');

            $response = $service->descargar($request->get('tenant'), $per, $tipo, $formato);

            return $this->streamBinary(
                $response,
                "resumen-{$periodo}-{$tipo->value}.{$formato->extension()}",
            );
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * GET /v1/sire/rce/constancia?nom_constancia=LE{ruc}{per}...pdf
     * Descarga directa del PDF (5.49).
     */
    public function constancia(
        Request $request,
        ConstanciaService $service,
    ): StreamedResponse|JsonResponse {
        $validated = $request->validate([
            'nom_constancia' => 'required|string|max:200',
        ]);

        try {
            $response = $service->descargar($request->get('tenant'), $validated['nom_constancia']);

            return $this->streamBinary(
                $response,
                $validated['nom_constancia'],
                'application/pdf',
            );
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        }
    }

    // ======================================================================
    // Utilidades privadas
    // ======================================================================

    private function parsePeriodo(string $periodo): PeriodoTributario
    {
        return PeriodoTributario::fromString($periodo);
    }

    private function parseFormato(string $key): CodTipoArchivo
    {
        return match ($key) {
            'csv'   => CodTipoArchivo::CSV,
            'excel' => CodTipoArchivo::EXCEL,
            default => CodTipoArchivo::TXT,
        };
    }

    private function parseTipoResumen(string $key): CodTipoResumen
    {
        return match ($key) {
            'preliminar'            => CodTipoResumen::PRELIMINAR,
            'no_incluidos_excluidos'=> CodTipoResumen::NO_INCLUIDOS_EXCLUIDOS,
            'registro'              => CodTipoResumen::REGISTRO,
            'preliminar_registrado' => CodTipoResumen::PRELIMINAR_REGISTRADO,
            'ajustes_posteriores'   => CodTipoResumen::AJUSTES_POSTERIORES,
            'no_domiciliados'       => CodTipoResumen::NO_DOMICILIADOS,
            default                 => CodTipoResumen::PROPUESTA,
        };
    }

    private function streamBinary(
        \Psr\Http\Message\ResponseInterface $response,
        string $filename,
        string $contentType = null,
    ): StreamedResponse {
        $contentType ??= $response->getHeaderLine('Content-Type') ?: 'application/octet-stream';

        return response()->stream(
            function () use ($response) {
                $stream = $response->getBody();
                while (! $stream->eof()) {
                    echo $stream->read(8192);
                }
            },
            Response::HTTP_OK,
            [
                'Content-Type'        => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
        );
    }
}
