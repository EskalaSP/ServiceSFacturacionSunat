<?php

namespace App\Actions\Documents;

use App\Models\Tenant;
use App\Services\Greenter\GreenterService;

class CreateVoidedAction
{
    public function execute(Tenant $tenant, array $data): array
    {
        $service = new GreenterService($tenant);
        $voided = $service->buildVoided($data);
        $result = $service->send($voided);

        if ($result['success'] && ! empty($result['ticket'])) {
            return [
                'success' => true,
                'ticket' => $result['ticket'],
                'xml' => $result['xml'],
                'message' => 'Comunicación de baja enviada. Use el ticket para consultar el estado.',
            ];
        }

        return [
            'success' => false,
            'error_code' => $result['error_code'] ?? null,
            'error_message' => $result['error_message'] ?? 'Error al enviar comunicación de baja',
        ];
    }
}
