<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Tenant;

class ClientResolverService
{
    public function resolve(Tenant $tenant, array $clientData): Client
    {
        $updateData = [
            'razon_social' => $clientData['razon_social'],
            'direccion' => $clientData['direccion'] ?? null,
        ];

        // Solo actualizar email/telefono si se proporcionan (no sobreescribir existentes con null)
        if (! empty($clientData['email'])) {
            $updateData['email'] = $clientData['email'];
        }
        if (! empty($clientData['telefono'])) {
            $updateData['telefono'] = $clientData['telefono'];
        }

        return Client::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tipo_documento' => $clientData['tipo_doc'],
                'numero_documento' => $clientData['num_doc'],
            ],
            $updateData
        );
    }
}
