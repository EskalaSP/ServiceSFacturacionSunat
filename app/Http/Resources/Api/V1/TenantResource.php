<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ruc' => $this->ruc,
            'razon_social' => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'direccion' => $this->direccion,
            'entorno' => $this->environment,
            'plan' => $this->plan,
            'max_documentos_mes' => $this->max_documents_month,
            'documentos_este_mes' => $this->documentsThisMonth(),
            'activo' => $this->is_active,
            'tiene_certificado' => ! empty($this->certificate_path),
            'telefonos' => $this->telefonos ?? [],
            'emails' => $this->emails ?? [],
            'cuentas_bancarias' => $this->cuentas_bancarias ?? [],
            'billeteras_digitales' => $this->billeteras_digitales ?? [],
            'mensaje_agradecimiento' => $this->mensaje_agradecimiento,
            'mensaje_promocional' => $this->mensaje_promocional,
            'tiene_webhook' => ! empty($this->webhook_url),
            'creado_en' => $this->created_at->toIso8601String(),
        ];
    }
}
