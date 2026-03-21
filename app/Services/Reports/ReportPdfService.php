<?php

namespace App\Services\Reports;

use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdfService
{
    public function generate(string $view, array $data, Tenant $tenant, string $orientation = 'portrait'): string
    {
        $data['tenant'] = [
            'ruc' => $tenant->ruc,
            'razon_social' => $tenant->razon_social,
            'nombre_comercial' => $tenant->nombre_comercial ?? $tenant->razon_social,
            'direccion' => $tenant->direccion,
            'logo_base64' => $this->getLogoBase64($tenant),
        ];

        $data['generated_at'] = now()->format('d/m/Y H:i:s');

        $pdf = Pdf::loadView("pdf.reports.{$view}", $data)
            ->setPaper('a4', $orientation)
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96);

        return $pdf->output();
    }

    private function getLogoBase64(Tenant $tenant): ?string
    {
        if (! $tenant->logo_path) {
            return null;
        }

        $path = storage_path('app/public/' . $tenant->logo_path);
        if (! file_exists($path)) {
            $path = storage_path('app/' . $tenant->logo_path);
        }
        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        $mime = mime_content_type($path);

        return "data:{$mime};base64," . base64_encode($content);
    }
}
