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
            'environment' => $this->environment,
            'plan' => $this->plan,
            'max_documents_month' => $this->max_documents_month,
            'documents_this_month' => $this->documentsThisMonth(),
            'is_active' => $this->is_active,
            'has_certificate' => ! empty($this->certificate_path),
            'has_webhook' => ! empty($this->webhook_url),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
