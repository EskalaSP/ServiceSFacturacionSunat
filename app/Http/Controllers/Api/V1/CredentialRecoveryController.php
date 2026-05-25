<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Mail\CredentialRecoveryMail;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CredentialRecoveryController extends Controller
{
    use ApiResponse;

    public function solicitar(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required_without:ruc|nullable|email|max:100',
            'ruc'   => 'required_without:email|nullable|digits:11',
        ]);

        // Rate limit: 3 intentos por IP cada 10 minutos
        $rateLimitKey = 'credential-recovery:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return $this->error("Demasiados intentos. Intenta de nuevo en {$seconds} segundos.", 429);
        }
        RateLimiter::hit($rateLimitKey, 600);

        $tenant = null;

        if ($request->filled('ruc')) {
            $tenant = Tenant::where('ruc', $request->input('ruc'))
                ->where('is_active', true)
                ->first();
        } elseif ($request->filled('email')) {
            $email = strtolower(trim($request->input('email')));
            $tenant = Tenant::where('is_active', true)
                ->whereJsonContains('emails', $email)
                ->first();

            // También buscar en el usuario vinculado
            if (! $tenant) {
                $tenant = Tenant::where('is_active', true)
                    ->whereHas('user', fn ($q) => $q->where('email', $email))
                    ->first();
            }
        }

        // Respuesta genérica para no revelar si el RUC/email existe
        if (! $tenant) {
            return $this->success(
                null,
                'Si el RUC o email está registrado, recibirás las instrucciones en breve.'
            );
        }

        // Obtener email de destino
        $emailDestino = $this->resolveEmailDestino($tenant);

        if (! $emailDestino) {
            return $this->error(
                'No hay un email registrado para esta empresa. Contacta al soporte.',
                422
            );
        }

        // Generar token de un solo uso, válido 30 minutos
        $token = Str::random(64);
        Cache::put("credential_recovery:{$token}", $tenant->id, now()->addMinutes(30));

        Mail::to($emailDestino)->send(new CredentialRecoveryMail($tenant, $token));

        return $this->success(
            null,
            'Si el RUC o email está registrado, recibirás las instrucciones en breve.'
        );
    }

    public function verificar(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $cacheKey = 'credential_recovery:' . $request->input('token');
        $tenantId = Cache::get($cacheKey);

        if (! $tenantId) {
            return $this->error('Token inválido o expirado.', 422);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->is_active) {
            Cache::forget($cacheKey);
            return $this->error('Token inválido o expirado.', 422);
        }

        // Token de un solo uso: borrar antes de regenerar
        Cache::forget($cacheKey);
        Cache::forget("tenant:key:{$tenant->api_key}");

        $newApiKey    = Str::random(64);
        $rawSecret    = Str::random(64);
        $newApiSecret = hash('sha256', $rawSecret);

        $tenant->update([
            'api_key'    => $newApiKey,
            'api_secret' => $newApiSecret,
        ]);

        return $this->success([
            'api_key'    => $newApiKey,
            'api_secret' => $rawSecret,
            'aviso'      => 'Guarda estas credenciales ahora. El api_secret NO se volverá a mostrar.',
        ], 'Credenciales regeneradas exitosamente.');
    }

    private function resolveEmailDestino(Tenant $tenant): ?string
    {
        // 1. Emails del tenant (array JSON)
        if (! empty($tenant->emails) && is_array($tenant->emails)) {
            return $tenant->emails[0];
        }

        // 2. Email del usuario vinculado
        if ($tenant->user && $tenant->user->email) {
            return $tenant->user->email;
        }

        return null;
    }
}
