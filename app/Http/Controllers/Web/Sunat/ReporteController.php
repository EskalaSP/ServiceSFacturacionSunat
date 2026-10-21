<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Services\Reports\RegistroVentasExcel;
use App\Services\Reports\ReportPdfService;
use App\Services\Reports\ReportService;
use App\Services\Tenancy\EmpresaActiva;
use App\Support\Reports\DocumentoFiltros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reportes de la empresa activa (para el dueño). Reutiliza ReportService. */
class ReporteController extends Controller
{
    /** Tope de documentos para el PDF (dompdf renderiza todo en memoria); Excel no tiene tope. */
    private const MAX_DOCUMENTOS_PDF = 3000;

    public function index(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        return Inertia::render('sunat/reportes/index', []);
    }

    public function registroVentas(Request $request, ReportService $service): JsonResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('ver-reportes', $tenant);

        $filters = $this->validarFiltros($request);

        try {
            $data = $service->registroVentas($tenant, $filters);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function registroVentasPdf(Request $request, ReportService $service, ReportPdfService $pdf): \Illuminate\Http\Response|JsonResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('ver-reportes', $tenant);

        $data = $service->registroVentas($tenant, $this->validarFiltros($request));

        if ($data['total_documentos'] > self::MAX_DOCUMENTOS_PDF) {
            return response()->json([
                'ok' => false,
                'error' => 'El reporte tiene '.$data['total_documentos'].' documentos; para el PDF acota el rango (máximo '.self::MAX_DOCUMENTOS_PDF.') o usa Excel.',
            ], 422);
        }

        return response($pdf->generate('registro_ventas', $data, $tenant, 'landscape'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->nombreArchivo($tenant->ruc, $data, 'pdf').'"',
        ]);
    }

    public function registroVentasExcel(Request $request, ReportService $service, RegistroVentasExcel $excel): BinaryFileResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('ver-reportes', $tenant);

        $data = $service->registroVentas($tenant, $this->validarFiltros($request));
        $ruta = $excel->generar($data, $tenant);

        return response()->download($ruta, $this->nombreArchivo($tenant->ruc, $data, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function validarFiltros(Request $request): array
    {
        $request->merge(DocumentoFiltros::prepararEntrada($request->all()));

        return $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde', function ($attr, $value, $fail) use ($request) {
                $desde = strtotime((string) $request->input('fecha_desde'));
                $hasta = strtotime((string) $value);
                if ($desde && $hasta && ($hasta - $desde) / 86400 > 366) {
                    $fail('El rango máximo permitido es de 1 año (366 días).');
                }
            }],
        ] + DocumentoFiltros::reglas());
    }

    private function nombreArchivo(string $ruc, array $data, string $ext): string
    {
        return "registro-ventas_{$ruc}_{$data['periodo']['desde']}_{$data['periodo']['hasta']}.{$ext}";
    }
}
