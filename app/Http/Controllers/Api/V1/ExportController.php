<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ExportController extends Controller
{
    use ApiResponse;

    private const TIPOS_VALIDOS = ['facturas', 'boletas', 'notas-credito', 'notas-debito'];

    private const MODELO_MAP = [
        'facturas'      => Invoice::class,
        'boletas'       => Boleta::class,
        'notas-credito' => CreditNote::class,
        'notas-debito'  => DebitNote::class,
    ];

    /**
     * GET /comprobantes/exportar-zip
     *
     * Parámetros:
     *   fecha_desde  (Y-m-d, requerido)
     *   fecha_hasta  (Y-m-d, requerido)
     *   tipo         xml|pdf|ambos  (default: xml)
     *   documentos   facturas,boletas,notas-credito,notas-debito  (default: todos)
     *   sucursal_id  int  (opcional)
     *   estado       aceptado|pendiente|rechazado|todos  (default: todos)
     */
    public function zip(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $tenant = $request->get('tenant');

        // --- Validación ---
        $desde = $request->query('fecha_desde');
        $hasta = $request->query('fecha_hasta');

        if (! $desde || ! $hasta) {
            return $this->error('Los parámetros fecha_desde y fecha_hasta son requeridos (formato Y-m-d).', 422);
        }

        try {
            $fechaDesde = Carbon::parse($desde)->startOfDay();
            $fechaHasta = Carbon::parse($hasta)->endOfDay();
        } catch (\Throwable) {
            return $this->error('Formato de fecha inválido. Use Y-m-d (ej: 2026-05-01).', 422);
        }

        if ($fechaDesde->gt($fechaHasta)) {
            return $this->error('fecha_desde no puede ser mayor que fecha_hasta.', 422);
        }

        if ($fechaDesde->diffInDays($fechaHasta) > 366) {
            return $this->error('El rango máximo permitido es 366 días.', 422);
        }

        $tipoArchivo = $request->query('tipo', 'xml');
        if (! in_array($tipoArchivo, ['xml', 'pdf', 'ambos'], true)) {
            return $this->error('El parámetro tipo debe ser xml, pdf o ambos.', 422);
        }

        $documentosParam = $request->query('documentos', 'facturas,boletas,notas-credito,notas-debito');
        $tiposDoc = array_filter(
            array_map('trim', explode(',', (string) $documentosParam)),
            fn ($t) => in_array($t, self::TIPOS_VALIDOS, true)
        );

        if (empty($tiposDoc)) {
            return $this->error('Ningún tipo de documento válido especificado. Use: ' . implode(', ', self::TIPOS_VALIDOS), 422);
        }

        $sucursalId = $request->query('sucursal_id');
        $estado     = $request->query('estado', 'todos');

        // --- Recolectar archivos ---
        $archivos = [];

        foreach ($tiposDoc as $tipo) {
            $modelClass = self::MODELO_MAP[$tipo];

            $query = $modelClass::where('tenant_id', $tenant->id)
                ->whereBetween('fecha_emision', [$fechaDesde->toDateString(), $fechaHasta->toDateString()]);

            if ($sucursalId) {
                $query->where('sucursal_id', (int) $sucursalId);
            }

            if ($estado !== 'todos') {
                $query->where('sunat_status', $estado);
            }

            $docs = $query->get(['id', 'serie', 'correlativo', 'fecha_emision', 'xml_path', 'pdf_path', 'sunat_status']);

            foreach ($docs as $doc) {
                $numero = $doc->serie . '-' . str_pad((string) $doc->correlativo, 6, '0', STR_PAD_LEFT);
                $folder = $tipo . '/' . $doc->fecha_emision->format('Y-m');

                if (in_array($tipoArchivo, ['xml', 'ambos'], true) && $doc->xml_path) {
                    if (Storage::disk('public')->exists($doc->xml_path)) {
                        $archivos[] = [
                            'disk_path' => $doc->xml_path,
                            'zip_name'  => "{$folder}/{$numero}.xml",
                        ];
                    }
                }

                if (in_array($tipoArchivo, ['pdf', 'ambos'], true) && $doc->pdf_path) {
                    if (Storage::disk('public')->exists($doc->pdf_path)) {
                        $archivos[] = [
                            'disk_path' => $doc->pdf_path,
                            'zip_name'  => "{$folder}/{$numero}.pdf",
                        ];
                    }
                }
            }
        }

        if (empty($archivos)) {
            return $this->error(
                'No se encontraron archivos para los criterios especificados. ' .
                'Verifique que los documentos tengan XML/PDF generados y estén dentro del rango de fechas.',
                404
            );
        }

        // --- Generar ZIP en un archivo temporal ---
        $tmpPath = tempnam(sys_get_temp_dir(), 'export_') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->error('No se pudo crear el archivo ZIP.', 500);
        }

        $diskRoot = Storage::disk('public')->path('');

        foreach ($archivos as $archivo) {
            $absolutePath = $diskRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $archivo['disk_path']);
            $zip->addFile($absolutePath, $archivo['zip_name']);
        }

        $zip->close();

        // --- Nombre del archivo de descarga ---
        $nombreZip = sprintf(
            'comprobantes_%s_%s_%s_%s.zip',
            $tenant->ruc,
            str_replace('-', '', $desde),
            str_replace('-', '', $hasta),
            $tipoArchivo
        );

        return response()->streamDownload(function () use ($tmpPath) {
            $handle = fopen($tmpPath, 'rb');
            while (! feof($handle)) {
                echo fread($handle, 8192);
                ob_flush();
            }
            fclose($handle);
            @unlink($tmpPath);
        }, $nombreZip, [
            'Content-Type'        => 'application/zip',
            'Content-Length'      => filesize($tmpPath),
            'Content-Disposition' => "attachment; filename=\"{$nombreZip}\"",
        ]);
    }
}
