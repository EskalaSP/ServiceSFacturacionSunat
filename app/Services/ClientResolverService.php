<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Tenant;

class ClientResolverService
{
    public function resolve(Tenant $tenant, array $clientData): Client
    {
        return Client::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tipo_documento' => $clientData['tipo_doc'],
                'numero_documento' => $clientData['num_doc'],
            ],
            [
                'razon_social' => $clientData['razon_social'],
                'direccion' => $clientData['direccion'] ?? null,
                'email' => $clientData['email'] ?? null,
            ]
        );
    }
}
