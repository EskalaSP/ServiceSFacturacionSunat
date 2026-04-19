<?php

namespace App\Actions\Documents;

use App\Models\Tenant;
use App\Services\Greenter\GreenterService;
use Illuminate\Support\Facades\Storage;

class CreateSummaryAction
{
    public function execute(Tenant $tenant, array $data): array
    {
        $service = new GreenterService($tenant);
        $summary = $service->buildSummary($data);
        $result = $service->send($summary);

        // Greenter genera ID con fecResumen (que es fecha_envio/IssueDate)
        $fechaId = str_replace('-', '', $data['fecha_envio']);
        $identifier = "RC-{$fechaId}-{$data['correlativo']}";

        // Guardar XML en disco
        $xmlPath = null;
        if (! empty($result['xml'])) {
            $fecha = $data['fecha_referencia'];
            $xmlPath = "{$tenant->ruc}/0000/{$fecha}/xml/{$identifier}.xml";
            Storage::disk('public')->put($xmlPath, $result['xml']);
        }

        if ($result['success'] && ! empty($result['ticket'])) {
            return [
                'estado' => 'exito',
                'identifier' => $identifier,
                'ticket' => $result['ticket'],
                'xml_path' => $xmlPath,
            ];
        }

        return [
            'estado' => 'error',
            'identifier' => $identifier,
            'codigo_error' => $result['error_code'] ?? null,
            'error_message' => $result['error_message'] ?? 'Error al enviar resumen',
            'xml_path' => $xmlPath,
        ];
    }
}
