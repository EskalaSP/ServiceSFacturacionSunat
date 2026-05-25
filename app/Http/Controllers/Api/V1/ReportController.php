<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportRequest;
use App\Http\Traits\ApiResponse;
use App\Services\Reports\ReportPdfService;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReportService $reportService,
        private ReportPdfService $pdfService,
    ) {}

    public function registroVentas(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        return $this->cachedReport($request, 'registro-ventas', 'registro_ventas', 'registroVentas', 'Registro de ventas generado.', 'landscape');
    }

    public function ventasConsolidado(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        return $this->cachedReport($request, 'ventas-consolidado', 'ventas_consolidado', 'ventasConsolidado', 'Ventas consolidado generado.');
    }

    public function notas(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        return $this->cachedReport($request, 'notas-credito-debito', 'notas', 'notas', 'Reporte de notas generado.');
    }

    public function cobranzas(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        return $this->cachedReport($request, 'cobranzas', 'cobranzas', 'cobranzas', 'Reporte de cobranzas generado.');
    }

    public function documentosInternos(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        return $this->cachedReport($request, 'documentos-internos', 'documentos_internos', 'documentosInternos', 'Reporte de documentos internos generado.');
    }

    public function porCliente(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $filters = $request->validated();

        if (empty($filters['client_num_doc'])) {
            return $this->error('El filtro client_num_doc es requerido para este reporte.', 422);
        }

        return $this->cachedReport($request, 'estado-cuenta-' . $filters['client_num_doc'], 'por_cliente', 'porCliente', 'Estado de cuenta generado.');
    }

    public function porSucursal(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        return $this->cachedReport($request, 'comparativo-sucursales', 'por_sucursal', 'porSucursal', 'Comparativo por sucursal generado.', 'landscape');
    }

    private function cachedReport(
        ReportRequest $request,
        string $pdfFilename,
        string $pdfView,
        string $serviceMethod,
        string $successMsg,
        string $orientation = 'portrait',
    ): JsonResponse|\Illuminate\Http\Response {
        $tenant = $request->get('tenant');
        $filters = $request->validated();

        // Los PDFs no se cachean
        if (($filters['formato'] ?? 'json') === 'pdf') {
            $data = $this->reportService->{$serviceMethod}($tenant, $filters);
            return $this->pdfResponse(
                $this->pdfService->generate($pdfView, $data, $tenant, $orientation),
                $pdfFilename
            );
        }

        // Cachear datos del reporte JSON por 5 minutos
        $cacheKey = "report:{$tenant->id}:{$serviceMethod}:" . md5(json_encode($filters));
        $data = Cache::remember($cacheKey, 300, fn () => $this->reportService->{$serviceMethod}($tenant, $filters));

        return $this->success($data, $successMsg);
    }

    private function pdfResponse(string $content, string $filename): \Illuminate\Http\Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}-" . now()->format('Y-m-d') . ".pdf\"",
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
