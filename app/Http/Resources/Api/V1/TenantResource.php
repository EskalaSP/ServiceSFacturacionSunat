<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'ubigeo' => $this->ubigeo,
            'departamento' => $this->departamento,
            'provincia' => $this->provincia,
            'distrito' => $this->distrito,
            'sol_user' => $this->sol_user,
            'tiene_sol_pass' => ! empty($this->sol_pass),
            'client_id' => $this->client_id,
            'tiene_client_secret' => ! empty($this->client_secret),
            'webhook_url' => $this->webhook_url,
            'entorno' => $this->environment,
            'plan' => $this->plan,
            'tax_regime' => $this->tax_regime,
            'igv_rate_override' => $this->igv_rate_override !== null ? (float) $this->igv_rate_override : null,
            'nrus_categoria' => $this->nrus_categoria,
            'tasa_igv_vigente' => (new \App\Services\TaxRateService())->currentRateFor($this->resource),
            'max_documentos_mes' => $this->max_documents_month,
            'documentos_este_mes' => $this->documentsThisMonth(),
            'activo' => $this->is_active,
            'logo_url' => $this->logo_path
                ? Storage::url($this->logo_path) . '?v=' . ($this->updated_at?->timestamp ?? time())
                : null,
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
