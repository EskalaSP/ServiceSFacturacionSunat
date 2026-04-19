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
                'estado' => 'exito',
                'ticket' => $result['ticket'],
                'xml' => $result['xml'],
                'mensaje' => 'Comunicación de baja enviada. Use el ticket para consultar el estado.',
            ];
        }

        return [
            'estado' => 'error',
            'codigo_error' => $result['error_code'] ?? null,
            'error_message' => $result['error_message'] ?? 'Error al enviar comunicación de baja',
        ];
    }
}
