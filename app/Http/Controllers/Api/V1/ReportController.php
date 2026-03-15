<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportRequest;
use App\Http\Traits\ApiResponse;
use App\Services\Reports\ReportPdfService;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReportService $reportService,
        private ReportPdfService $pdfService,
    ) {}

    public function registroVentas(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $tenant = $request->get('tenant');
        $filters = $request->validated();
        $data = $this->reportService->registroVentas($tenant, $filters);

        if (($filters['formato'] ?? 'json') === 'pdf') {
            return $this->pdfResponse(
                $this->pdfService->generate('registro_ventas', $data, $tenant, 'landscape'),
                'registro-ventas'
            );
        }

        return $this->success($data, 'Registro de ventas generado.');
    }

    public function ventasConsolidado(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $tenant = $request->get('tenant');
        $filters = $request->validated();
        $data = $this->reportService->ventasConsolidado($tenant, $filters);

        if (($filters['formato'] ?? 'json') === 'pdf') {
            return $this->pdfResponse(
                $this->pdfService->generate('ventas_consolidado', $data, $tenant),
                'ventas-consolidado'
            );
        }

        return $this->success($data, 'Ventas consolidado generado.');
    }

    public function notas(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $tenant = $request->get('tenant');
        $filters = $request->validated();
        $data = $this->reportService->notas($tenant, $filters);

        if (($filters['formato'] ?? 'json') === 'pdf') {
            return $this->pdfResponse(
                $this->pdfService->generate('notas', $data, $tenant),
                'notas-credito-debito'
            );
        }

        return $this->success($data, 'Reporte de notas generado.');
    }

    public function cobranzas(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $tenant = $request->get('tenant');
        $filters = $request->validated();
        $data = $this->reportService->cobranzas($tenant, $filters);

        if (($filters['formato'] ?? 'json') === 'pdf') {
            return $this->pdfResponse(
                $this->pdfService->generate('cobranzas', $data, $tenant),
                'cobranzas'
            );
        }

        return $this->success($data, 'Reporte de cobranzas generado.');
    }

    public function documentosInternos(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $tenant = $request->get('tenant');
        $filters = $request->validated();
        $data = $this->reportService->documentosInternos($tenant, $filters);

        if (($filters['formato'] ?? 'json') === 'pdf') {
            return $this->pdfResponse(
                $this->pdfService->generate('documentos_internos', $data, $tenant),
                'documentos-internos'
            );
        }

        return $this->success($data, 'Reporte de documentos internos generado.');
    }

    public function porCliente(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $tenant = $request->get('tenant');
        $filters = $request->validated();

        if (empty($filters['client_num_doc'])) {
            return $this->error('El filtro client_num_doc es requerido para este reporte.', 422);
        }

        $data = $this->reportService->porCliente($tenant, $filters);

        if (($filters['formato'] ?? 'json') === 'pdf') {
            return $this->pdfResponse(
                $this->pdfService->generate('por_cliente', $data, $tenant),
                'estado-cuenta-' . $filters['client_num_doc']
            );
        }

        return $this->success($data, 'Estado de cuenta generado.');
    }

    public function porSucursal(ReportRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $tenant = $request->get('tenant');
        $filters = $request->validated();
        $data = $this->reportService->porSucursal($tenant, $filters);

        if (($filters['formato'] ?? 'json') === 'pdf') {
            return $this->pdfResponse(
                $this->pdfService->generate('por_sucursal', $data, $tenant, 'landscape'),
                'comparativo-sucursales'
            );
        }

        return $this->success($data, 'Comparativo por sucursal generado.');
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
