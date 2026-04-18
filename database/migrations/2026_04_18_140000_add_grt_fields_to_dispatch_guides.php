<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte para Guía de Remisión Transportista (GRT - tipo_documento 31).
 *
 * Según Catálogo 01 SUNAT:
 *   '09' = Guía de Remisión Remitente (GRR) — emite el remitente (quien envía)
 *   '31' = Guía de Remisión Transportista (GRT) — emite la empresa de transporte
 *
 * Diferencias clave:
 *  - GRR: DespatchSupplierParty = remitente; destinatario como DeliveryCustomerParty
 *  - GRT: DespatchSupplierParty = transportista; remitente como DespatchParty dentro de Shipment/Delivery/Despatch;
 *         AdditionalDocumentReference OBLIGATORIO (debe referenciar factura/boleta/DAM/GRR)
 *
 * Campos nuevos:
 *  - tipo_documento: '09' default (compat retro) o '31' para GRT
 *  - doc_relacionado: JSON con docs referenciados (obligatorio en GRT)
 *  - datos_subcontratador: JSON si la carga está subcontratada (solo GRT)
 *  - datos_pagador_flete: JSON si un tercero paga el flete (solo GRT)
 *  - autorizacion_especial: JSON con entidad (Cat D-37) + nro_autorizacion (GRT y GRR especial)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->string('tipo_documento', 2)->default('09')->after('document_id');
            $table->json('remitente')->nullable()->after('num_bultos');
            $table->json('doc_relacionado')->nullable()->after('remitente');
            $table->json('datos_subcontratador')->nullable()->after('doc_relacionado');
            $table->json('datos_pagador_flete')->nullable()->after('datos_subcontratador');
            $table->json('autorizacion_especial')->nullable()->after('datos_pagador_flete');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_guides', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_documento',
                'remitente',
                'doc_relacionado',
                'datos_subcontratador',
                'datos_pagador_flete',
                'autorizacion_especial',
            ]);
        });
    }
};
