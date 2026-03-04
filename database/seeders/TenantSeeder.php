<?php

namespace Database\Seeders;

use App\Models\Serie;
use App\Models\Tenant;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $storage = new DocumentStorageService();

        // Copiar certificado de prueba a storage privado
        $certSource = base_path('greenter-documentacion/resources/cert.pem');
        $certPath = null;

        if (file_exists($certSource)) {
            // Crear tenant temporal para obtener la ruta
            $certContent = file_get_contents($certSource);
        }

        // Crear tenant de prueba
        $tenant = Tenant::create([
            'ruc' => '20123456789',
            'razon_social' => 'EMPRESA DEMO S.A.C.',
            'nombre_comercial' => 'DEMO SAC',
            'direccion' => 'AV. DEMO 123, LIMA',
            'ubigeo' => '150101',
            'sol_user' => 'MODDATOS',
            'sol_pass' => 'moddatos',
            'certificate_path' => null,
            'environment' => 'beta',
            'plan' => 'business',
            'max_documents_month' => 99999,
            'is_active' => true,
            'api_key' => 'demo-api-key-2026-facturacion-electronica-sunat',
            'api_secret' => hash('sha256', 'demo-secret-2026'),
        ]);

        // Guardar certificado en storage/app/private/certificates/{ruc}/
        if (isset($certContent)) {
            $certPath = $storage->storeCertificate($tenant, $certContent);
            $tenant->update(['certificate_path' => $certPath]);
        }

        // Series para facturas
        Serie::create(['tenant_id' => $tenant->id, 'tipo_documento' => '01', 'serie' => 'F001']);
        Serie::create(['tenant_id' => $tenant->id, 'tipo_documento' => '01', 'serie' => 'F002']);

        // Series para boletas
        Serie::create(['tenant_id' => $tenant->id, 'tipo_documento' => '03', 'serie' => 'B001']);

        // Series para notas de crédito
        Serie::create(['tenant_id' => $tenant->id, 'tipo_documento' => '07', 'serie' => 'FC01']);
        Serie::create(['tenant_id' => $tenant->id, 'tipo_documento' => '07', 'serie' => 'BC01']);

        // Series para notas de débito
        Serie::create(['tenant_id' => $tenant->id, 'tipo_documento' => '08', 'serie' => 'FD01']);

        // Series para guías de remisión
        Serie::create(['tenant_id' => $tenant->id, 'tipo_documento' => '09', 'serie' => 'T001']);

        $this->command->info('Tenant de prueba creado:');
        $this->command->info("  RUC: {$tenant->ruc}");
        $this->command->info("  API Key: {$tenant->api_key}");
        $this->command->info('  API Secret: '.hash('sha256', 'demo-secret-2026'));
        $this->command->info('  Environment: beta');
        $this->command->info("  Certificado: {$tenant->certificate_path}");
    }
}
