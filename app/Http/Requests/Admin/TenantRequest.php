<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant')?->id;
        $creando  = $this->isMethod('POST');
        // En UPDATE los campos operativos son `sometimes` para que un PUT
        // parcial (ej. sólo cambiar razón social) no exija reenviar todo.
        $reqOSometimes = $creando ? 'required' : 'sometimes';

        return [
            // ── Identidad ───────────────────────────────────────────────
            'ruc' => [
                $reqOSometimes,
                'string', 'size:11', 'regex:/^(10|15|17|20)\d{9}$/',
                Rule::unique('tenants', 'ruc')->ignore($tenantId),
            ],
            'razon_social' => [$reqOSometimes, 'string', 'max:255'],
            'nombre_comercial' => 'nullable|string|max:255',

            // ── Ubicación ───────────────────────────────────────────────
            'direccion' => 'nullable|string|max:500',
            'ubigeo' => 'nullable|string|size:6',
            'departamento' => 'nullable|string|max:100',
            'provincia' => 'nullable|string|max:100',
            'distrito' => 'nullable|string|max:100',

            'telefonos' => 'nullable|array|max:5',
            'telefonos.*' => 'nullable|string|max:20',
            'emails' => 'nullable|array|max:5',
            'emails.*' => 'nullable|email|max:100',

            // ── Credenciales SUNAT ──────────────────────────────────────
            'sol_user' => [$reqOSometimes, 'string', 'max:50'],
            'sol_pass' => $creando ? 'required|string|min:4|max:100' : 'nullable|string|min:4|max:100',
            'environment' => [$reqOSometimes, Rule::in(['beta', 'production'])],

            'certificado' => 'nullable|file|extensions:pfx,p12,pem|max:2048',
            'contrasena_certificado' => 'nullable|string|max:100',

            // ── SIRE ────────────────────────────────────────────────────
            'sire_enabled' => 'nullable|boolean',
            'sire_client_id' => 'nullable|string|max:100',
            'sire_client_secret' => 'nullable|string|max:200',

            // ── Régimen tributario ──────────────────────────────────────
            'tax_regime' => [$reqOSometimes, Rule::in(['general', 'mype_restaurantes', 'nrus'])],
            'igv_rate_override' => 'nullable|numeric|between:0,30',
            'nrus_categoria' => 'nullable|in:1,2',

            // ── Plan y límites ──────────────────────────────────────────
            // Modo de emisión: 'plan' respeta los límites del plan;
            // 'unlimited' emite sin restricciones. Opcional en el form —
            // si no viene, el controller aplica la config global.
            'emission_mode' => ['nullable', Rule::in(['plan', 'unlimited'])],

            // El plan se valida contra la tabla `plans`, no un enum hardcoded,
            // porque los planes son 100% administrables desde /admin/planes.
            'plan' => [
                $reqOSometimes,
                Rule::exists('plans', 'slug')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'max_documents_month' => [$reqOSometimes, 'integer', 'min:0', 'max:99999'],

            // ── Comercial ───────────────────────────────────────────────
            'webhook_url' => 'nullable|url|max:500',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'mensaje_agradecimiento' => 'nullable|string|max:500',
            'mensaje_promocional' => 'nullable|string|max:500',

            'cuentas_bancarias' => 'nullable|array|max:5',
            'cuentas_bancarias.*.banco' => 'required_with:cuentas_bancarias.*|string|max:50',
            'cuentas_bancarias.*.tipo' => 'nullable|string|max:30',
            'cuentas_bancarias.*.numero' => 'required_with:cuentas_bancarias.*|string|max:30',
            'cuentas_bancarias.*.moneda' => 'nullable|in:PEN,USD',
            'cuentas_bancarias.*.cci' => 'nullable|string|max:30',

            'billeteras_digitales' => 'nullable|array|max:5',
            'billeteras_digitales.*.tipo' => 'required_with:billeteras_digitales.*|string|max:30',
            'billeteras_digitales.*.numero' => 'required_with:billeteras_digitales.*|string|max:30',

            // ── Asignación ──────────────────────────────────────────────
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.regex' => 'El RUC debe empezar con 10, 15, 17 o 20 (11 dígitos).',
            'ruc.size' => 'El RUC debe tener exactamente 11 dígitos.',
            'certificado.extensions' => 'El certificado debe ser un archivo .pfx, .p12 o .pem.',
            'certificado.max' => 'El certificado no puede pesar más de 2 MB.',
            'plan.exists' => 'El plan seleccionado no existe o está desactivado. Revisa /admin/planes.',
            'environment.in' => 'El entorno debe ser "beta" o "production".',
            'tax_regime.in' => 'El régimen debe ser general, mype_restaurantes o nrus.',
        ];
    }
}
