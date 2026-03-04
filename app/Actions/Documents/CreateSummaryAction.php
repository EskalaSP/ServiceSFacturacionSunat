<?php

namespace App\Actions\Documents;

use App\Jobs\CheckTicketStatus;
use App\Models\Document;
use App\Models\Tenant;
use App\Services\Greenter\GreenterService;

class CreateSummaryAction
{
    public function execute(Tenant $tenant, array $data): array
    {
        $service = new GreenterService($tenant);
        $summary = $service->buildSummary($data);
        $result = $service->send($summary);

        if ($result['success'] && ! empty($result['ticket'])) {
            return [
                'success' => true,
                'ticket' => $result['ticket'],
                'xml' => $result['xml'],
                'message' => 'Resumen enviado. Use el ticket para consultar el estado.',
            ];
        }

        return [
            'success' => false,
            'error_code' => $result['error_code'] ?? null,
            'error_message' => $result['error_message'] ?? 'Error al enviar resumen',
        ];
    }
}
