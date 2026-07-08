<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Autentica usuarios que llegan desde el SaaS principal mediante un
 * token firmado con HMAC-SHA256.
 *
 * Flujo:
 *   1. SaaS principal llama POST /api/bridge/auth para obtener un signed_url
 *   2. Redirige al usuario a GET /auth/from-saas?token=XXX
 *   3. Este controlador valida el token, crea/busca el usuario y crea sesión
 */
class SaasAuthController extends Controller
{
    private const TOKEN_TTL_SECONDS = 600; // 10 minutos

    /**
     * Crea o actualiza el tenant del usuario desde Zaresk.
     * Endpoint: POST /api/bridge/provision
     * Header:   X-Bridge-Key: {SAAS_BRIDGE_SECRET}
     */
    public function provision(Request $request): JsonResponse
    {
        $bridgeKey = config('app.saas_bridge_secret', '');

        if (empty($bridgeKey) || $request->header('X-Bridge-Key') !== $bridgeKey) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'email'    => 'required|email',
            'name'     => 'nullable|string|max:255',
            'ruc'      => 'required|string|size:11',
            'razon_social' => 'required|string|max:255',
            'sol_user' => 'required|string|max:20',
            'sol_pass' => 'required|string|max:50',
            'certificate' => 'nullable|file|max:4096',
            'certificate_password' => 'nullable|string|max:255',
        ]);

        // Crea o encuentra el usuario por email
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name'              => $data['name'] ?? 'Usuario',
                'password'          => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]
        );

        // Crea o actualiza el tenant por RUC
        $tenant = Tenant::firstOrNew(['ruc' => $data['ruc']]);

        $tenant->user_id     = $tenant->user_id ?? $user->id;
        $tenant->sol_user    = $data['sol_user'];
        $tenant->sol_pass    = $data['sol_pass'];
        $tenant->razon_social = $tenant->razon_social ?? ($data['razon_social'] ?? '');

        if (! $tenant->exists) {
            $tenant->environment  = 'beta';
            $tenant->plan         = 'free';
            $tenant->is_active    = true;
        }

        $tenant->save();
        
        // Handle Certificate Upload
        if ($request->hasFile('certificate')) {
            $certContent = file_get_contents($request->file('certificate')->getRealPath());
            $certService = new \App\Services\Storage\DocumentStorageService();
            $ext = $request->file('certificate')->getClientOriginalExtension();
            $certPath = $certService->storeCertificate($tenant, $certContent, 'cert.' . $ext);
            $tenant->certificate_path = $certPath;
        }
        
        if ($request->filled('certificate_password')) {
            $tenant->certificate_password = $data['certificate_password'];
        }
        
        if ($request->hasFile('certificate') || $request->filled('certificate_password')) {
            $tenant->save();
        }

        // Crear series por defecto si no existen
        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '01', 'serie' => 'F001'],
            ['correlativo' => 0, 'is_active' => true]
        );
        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '07', 'serie' => 'FC01'],
            ['correlativo' => 0, 'is_active' => true]
        );
        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '08', 'serie' => 'FD01'],
            ['correlativo' => 0, 'is_active' => true]
        );
        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '03', 'serie' => 'B001'],
            ['correlativo' => 0, 'is_active' => true]
        );
        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '07', 'serie' => 'BC01'],
            ['correlativo' => 0, 'is_active' => true]
        );
        Serie::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo_documento' => '08', 'serie' => 'BD01'],
            ['correlativo' => 0, 'is_active' => true]
        );

        return response()->json([
            'api_key'     => $tenant->api_key,
            'api_secret'  => $tenant->getRawOriginal('api_secret'),
            'ruc'         => $tenant->ruc,
            'razon_social'=> $tenant->razon_social ?? '',
            'environment' => $tenant->environment,
        ]);
    }

    public function deleteTenant(Request $request): JsonResponse
    {
        $bridgeKey = config('app.saas_bridge_secret', '');

        if (empty($bridgeKey) || $request->header('X-Bridge-Key') !== $bridgeKey) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate(['ruc' => 'required|string|size:11']);

        // Eliminar el tenant ignorando llaves foraneas temporalmente si es necesario
        // Pero Eloquent cascade or DB should handle it. 
        // We will just do a standard delete on Tenant, assuming constraints allow or cascade is set.
        // Actually, since there might be foreign key constraints, let's use a safe deletion.
        try {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Tenant::where('ruc', $request->ruc)->forceDelete();
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            return response()->json(['error' => 'Error al eliminar tenant: ' . $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Genera un token firmado para redirigir un usuario desde el SaaS principal.
     * Endpoint: POST /api/bridge/auth
     * Header:   X-Bridge-Key: {SAAS_BRIDGE_SECRET}
     */
    public function generateToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $bridgeKey = config('app.saas_bridge_secret', '');

        if (empty($bridgeKey) || $request->header('X-Bridge-Key') !== $bridgeKey) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'email' => 'required|email',
            'name'  => 'required|string|max:255',
        ]);

        $payload = json_encode([
            'email' => $request->input('email'),
            'name'  => $request->input('name'),
            'ts'    => time(),
        ]);

        $signature = hash_hmac('sha256', $payload, $bridgeKey);
        $token = base64_encode($signature . '|' . $payload);

        return response()->json([
            'redirect_url' => route('auth.from-saas', ['token' => $token]),
            'expires_in'   => self::TOKEN_TTL_SECONDS,
        ]);
    }

    /**
     * Valida el token firmado, inicia sesión y redirige al módulo SUNAT.
     * Endpoint: GET /auth/from-saas?token=XXX
     */
    public function login(Request $request): RedirectResponse
    {
        $rawToken = $request->query('token', '');

        if (empty($rawToken)) {
            abort(403, 'Token requerido.');
        }

        $bridgeKey = config('app.saas_bridge_secret', '');

        if (empty($bridgeKey)) {
            abort(500, 'SAAS_BRIDGE_SECRET no configurado.');
        }

        $decoded = base64_decode($rawToken, strict: true);

        if ($decoded === false || ! str_contains($decoded, '|')) {
            abort(403, 'Token inválido.');
        }

        [$signature, $payloadJson] = explode('|', $decoded, 2);

        if (! hash_equals(hash_hmac('sha256', $payloadJson, $bridgeKey), $signature)) {
            abort(403, 'Firma de token inválida.');
        }

        $payload = json_decode($payloadJson, true);

        if (
            ! is_array($payload)
            || empty($payload['email'])
            || empty($payload['ts'])
            || (time() - (int) $payload['ts']) > self::TOKEN_TTL_SECONDS
        ) {
            abort(403, 'Token expirado o malformado.');
        }

        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            [
                'name'              => $payload['name'] ?? 'Usuario',
                'password'          => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $tenant = $user->tenants()->first();

        if (! $tenant || empty($tenant->sol_user)) {
            return redirect()->route('sunat.configuracion')
                ->with('info', 'Bienvenido. Configura tus credenciales SUNAT para empezar.');
        }

        return redirect()->route('sunat.facturas.create');
    }
}
