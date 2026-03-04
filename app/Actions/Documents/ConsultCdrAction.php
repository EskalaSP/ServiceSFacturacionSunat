<?php

namespace App\Actions\Documents;

use App\Models\Tenant;
use App\Services\Greenter\GreenterService;

class ConsultCdrAction
{
    public function execute(Tenant $tenant, string $tipo, string $serie, int $numero): array
    {
        $service = new GreenterService($tenant);

        $env = $tenant->environment;
        $endpoint = config("facturacion.sunat.{$env}.consulta_cdr");

        return $service->getStatus("{$tenant->ruc}-{$tipo}-{$serie}-{$numero}", $endpoint);
    }
}
