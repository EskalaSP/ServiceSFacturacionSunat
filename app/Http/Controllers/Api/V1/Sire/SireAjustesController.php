<?php

namespace App\Http\Controllers\Api\V1\Sire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Sire\Enums\CodTipoArchivo;
use App\Sire\Enums\VariantAjuste;
use App\Sire\Exceptions\SireException;
use App\Sire\Services\Ajustes\AjustesPosterioresService;
use App\Sire\Support\PeriodoTributario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints para Ajustes Posteriores RCE (manual v22 sec. 5.18-5.29 y 5.45-5.48).
 *
 * Todas las rutas toman `variant` como parte del path:
 *   - actual
 *   - no-domiciliados
 *   - periodos-anteriores
 *   - periodos-anteriores-nd
 *
 * Acciones:
 *   POST /cargar    — TUS upload del archivo con los ajustes
 *   POST /enviar    — confirma/registra los ajustes cargados (ticket)
 *   GET  /descargar — solicita generación del archivo exportado (ticket)
 *   POST /eliminar  — elimina comprobantes específicos del ajuste
 */
class SireAjustesController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AjustesPosterioresService $service,
    ) {}

    /**
     * POST /v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/cargar
     */
    public function cargar(Request $request, string $periodo, string $variant): JsonResponse
    {
        $ctx = $this->parseContexto($periodo, $variant);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        $request->validate([
            'txt'       => 'required|string',
            'secuencia' => 'sometimes|integer|min:1|max:99999999',
        ]);

        try {
            $ticket = $this->service->cargar(
                tenant: $request->get('tenant'),
                periodo: $ctx['periodo'],
                variant: $ctx['variant'],
                txtContent: $request->input('txt'),
                secuencia: (int) $request->input('secuencia', 1),
            );

            return $this->success([
                'num_ticket'     => $ticket->num_ticket,
                'per_tributario' => $ticket->per_tributario,
                'variant'        => $ctx['variant']->value,
                'estado'         => $ticket->cod_estado_proceso,
                'archivo'        => $ticket->nom_archivo_importacion,
                'mensaje'        => 'Ajustes cargados. Siga el ticket para confirmar.',
            ], 'Upload aceptado', 202);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * POST /v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/enviar
     */
    public function enviar(Request $request, string $periodo, string $variant): JsonResponse
    {
        $ctx = $this->parseContexto($periodo, $variant);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        $request->validate([
            'num_ticket_carga' => 'required|string|size:16',
        ]);

        try {
            $ticket = $this->service->enviar(
                tenant: $request->get('tenant'),
                periodo: $ctx['periodo'],
                variant: $ctx['variant'],
                numTicketCarga: $request->input('num_ticket_carga'),
            );

            return $this->success([
                'num_ticket'     => $ticket->num_ticket,
                'per_tributario' => $ticket->per_tributario,
                'variant'        => $ctx['variant']->value,
                'estado'         => $ticket->cod_estado_proceso,
                'mensaje'        => 'Ajustes enviados. Sigue el ticket para ver el resultado.',
            ], 'Solicitud aceptada', 202);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * GET /v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/descargar
     */
    public function descargar(Request $request, string $periodo, string $variant): JsonResponse
    {
        $ctx = $this->parseContexto($periodo, $variant);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        $validated = $request->validate([
            'formato' => 'sometimes|in:txt,csv,excel',
        ]);

        $formato = match ($validated['formato'] ?? 'txt') {
            'csv'   => CodTipoArchivo::CSV,
            'excel' => CodTipoArchivo::EXCEL,
            default => CodTipoArchivo::TXT,
        };

        try {
            $ticket = $this->service->descargar(
                tenant: $request->get('tenant'),
                periodo: $ctx['periodo'],
                variant: $ctx['variant'],
                formato: $formato,
            );

            return $this->success([
                'num_ticket'     => $ticket->num_ticket,
                'per_tributario' => $ticket->per_tributario,
                'variant'        => $ctx['variant']->value,
                'formato'        => $formato->extension(),
                'mensaje'        => 'Generación de archivo solicitada. Sigue el ticket.',
            ], 'Solicitud aceptada', 202);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        }
    }

    /**
     * POST /v1/sire/rce/{periodo}/ajustes-posteriores/{variant}/eliminar
     */
    public function eliminar(Request $request, string $periodo, string $variant): JsonResponse
    {
        $ctx = $this->parseContexto($periodo, $variant);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        $validated = $request->validate([
            'cod_ajuste_posterior'             => 'required|string',
            'detalles'                         => 'required|array|min:1',
            'detalles.*.cod_tipo_cdp'          => 'required|string|size:2',
            'detalles.*.num_serie_cdp'         => 'required|string',
            'detalles.*.num_cdp'               => 'required|string',
            'detalles.*.cod_car'               => 'required|string',
            'detalles.*.id'                    => 'sometimes|string',
        ]);

        try {
            $respuesta = $this->service->eliminar(
                tenant: $request->get('tenant'),
                periodo: $ctx['periodo'],
                variant: $ctx['variant'],
                codAjustePosterior: $validated['cod_ajuste_posterior'],
                detalles: $validated['detalles'],
            );

            return $this->success([
                'variant'         => $ctx['variant']->value,
                'per_tributario'  => $ctx['periodo']->toString(),
                'eliminados'      => count($validated['detalles']),
                'respuesta_sunat' => $respuesta,
            ]);
        } catch (SireException $e) {
            return $this->error($e->getMessage(), $e->httpStatus, ['sunat_code' => $e->sunatCode]);
        }
    }

    // ==================================================================
    // Utilidades
    // ==================================================================

    /**
     * @return array{periodo: PeriodoTributario, variant: VariantAjuste}|JsonResponse
     */
    private function parseContexto(string $periodo, string $variant): array|JsonResponse
    {
        try {
            $per = PeriodoTributario::fromString($periodo);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $v = match ($variant) {
            'actual'                 => VariantAjuste::PERIODO_ACTUAL,
            'no-domiciliados'        => VariantAjuste::NO_DOMICILIADOS,
            'periodos-anteriores'    => VariantAjuste::PERIODOS_ANTERIORES,
            'periodos-anteriores-nd' => VariantAjuste::PERIODOS_ANTERIORES_ND,
            default                  => null,
        };

        if ($v === null) {
            return $this->error(
                'Variant inválido. Use: actual | no-domiciliados | periodos-anteriores | periodos-anteriores-nd',
                422,
            );
        }

        return ['periodo' => $per, 'variant' => $v];
    }
}
