<?php

declare(strict_types=1);

use App\Mail\CredentialRecoveryMail;
use App\Mail\TenantCredentialsMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Test end-to-end del panel /admin/empresas:
 * (1) crear empresa   → guarda todo + expone credenciales para descarga .txt
 * (2) editar campos   → persiste (bug reportado por el usuario)
 * (3) editar parcial  → PUT con solo un campo funciona
 * (4) regenerar creds → genera nuevas + invalida cache + expone para descarga
 * (5) regenerar siempre funciona (no depende de correo)
 */

function planesBasicos(): void
{
    Plan::create([
        'slug' => 'free',
        'name' => 'Free',
        'price_monthly' => 0,
        'is_active' => true,
        'sort_order' => 1,
        'limits' => ['documents_month' => 20],
        'features' => [],
    ]);
    Plan::create([
        'slug' => 'pro',
        'name' => 'Pro',
        'price_monthly' => 49,
        'is_active' => true,
        'sort_order' => 2,
        'limits' => ['documents_month' => 500],
        'features' => [],
    ]);
}

function adminLogueado(): User
{
    $admin = User::factory()->create(['is_admin' => true]);
    test()->actingAs($admin);
    return $admin;
}

// ── (1) CREAR ──────────────────────────────────────────────────────────────

test('crear empresa persiste todos los campos y expone credenciales para descarga', function () {
    planesBasicos();
    adminLogueado();
    Mail::fake();

    $payload = [
        'ruc' => '20512345678',
        'razon_social' => 'EMPRESA DEMO SAC',
        'nombre_comercial' => 'DEMO STORE',
        'direccion' => 'Av. Test 123',
        'ubigeo' => '150101',
        'departamento' => 'LIMA',
        'provincia' => 'LIMA',
        'distrito' => 'MIRAFLORES',
        'telefonos' => ['+51 999888777'],
        'emails' => ['contacto@demo.com'],
        'sol_user' => 'MODDATOS',
        'sol_pass' => 'MODDATOS',
        'environment' => 'beta',
        'tax_regime' => 'general',
        'plan' => 'pro',
        'max_documents_month' => 500,
        'webhook_url' => 'https://demo.com/hook',
        'mensaje_agradecimiento' => 'Gracias por comprar',
        'mensaje_promocional' => 'Síguenos',
        'is_active' => true,
    ];

    $response = $this->post('/admin/empresas', $payload);
    $response->assertRedirect();

    $tenant = Tenant::where('ruc', '20512345678')->firstOrFail();

    expect($tenant->nombre_comercial)->toBe('DEMO STORE')
        ->and($tenant->direccion)->toBe('Av. Test 123')
        ->and($tenant->telefonos)->toBe(['+51 999888777'])
        ->and($tenant->emails)->toBe(['contacto@demo.com'])
        ->and($tenant->webhook_url)->toBe('https://demo.com/hook')
        ->and($tenant->mensaje_agradecimiento)->toBe('Gracias por comprar')
        ->and($tenant->plan)->toBe('pro')
        ->and($tenant->max_documents_month)->toBe(500);

    // Suscripción activa asignada correctamente
    expect(Subscription::where('tenant_id', $tenant->id)
        ->where('status', Subscription::STATUS_ACTIVE)
        ->exists())->toBeTrue();

    // Las credenciales se exponen para descarga .txt (flash) Y se envían por
    // correo con el .txt adjunto (la empresa tiene email en el payload).
    $response->assertSessionHas('credenciales_nuevas');
    $cred = session('credenciales_nuevas');
    expect($cred['api_key'])->toBe($tenant->api_key)
        ->and($cred['api_secret'])->toBe($tenant->api_secret)
        ->and($cred['ruc'])->toBe($tenant->ruc);

    Mail::assertQueued(TenantCredentialsMail::class, function ($mail) use ($tenant) {
        return $mail->tenant->id === $tenant->id
            && $mail->motivo === 'creacion'
            && count($mail->attachments()) === 1; // credenciales van adjuntas
    });
});

// ── (2) EDITAR CAMPOS ──────────────────────────────────────────────────────

test('editar empresa persiste cambios en TODOS los campos', function () {
    planesBasicos();
    adminLogueado();
    Mail::fake();

    // Empresa inicial vacía (como DEMO 3 del usuario)
    $tenant = Tenant::factory()->create([
        'ruc' => '20161515640',
        'razon_social' => 'DEMO 3 API SUNAT SAC',
        'nombre_comercial' => null,
        'direccion' => 'AV. PRINCIPAL 123',
        'telefonos' => null,
        'emails' => null,
        'webhook_url' => null,
        'mensaje_agradecimiento' => null,
        'plan' => 'free',
        'max_documents_month' => 20,
    ]);

    $payload = [
        'ruc' => $tenant->ruc,
        'razon_social' => $tenant->razon_social,
        'nombre_comercial' => 'DEMO COMERCIAL NUEVO',
        'direccion' => $tenant->direccion,
        'ubigeo' => '150101',
        'departamento' => 'LIMA',
        'provincia' => 'LIMA',
        'distrito' => 'MIRAFLORES',
        'telefonos' => ['+51 111222333', '+51 999888777'],
        'emails' => ['nuevo@demo.com'],
        'sol_user' => 'MODDATOS',
        'environment' => 'production',
        'tax_regime' => 'general',
        'plan' => 'pro',
        'max_documents_month' => 500,
        'webhook_url' => 'https://demo.example.com/webhook',
        'mensaje_agradecimiento' => 'MENSAJE NUEVO',
        'mensaje_promocional' => 'PROMO NUEVA',
        'is_active' => true,
    ];

    $response = $this->put("/admin/empresas/{$tenant->id}", $payload);
    $response->assertRedirect();

    $tenant->refresh();

    expect($tenant->nombre_comercial)->toBe('DEMO COMERCIAL NUEVO')
        ->and($tenant->telefonos)->toBe(['+51 111222333', '+51 999888777'])
        ->and($tenant->emails)->toBe(['nuevo@demo.com'])
        ->and($tenant->environment)->toBe('production')
        ->and($tenant->plan)->toBe('pro')
        ->and($tenant->max_documents_month)->toBe(500)
        ->and($tenant->webhook_url)->toBe('https://demo.example.com/webhook')
        ->and($tenant->mensaje_agradecimiento)->toBe('MENSAJE NUEVO')
        ->and($tenant->mensaje_promocional)->toBe('PROMO NUEVA');
});

// ── (3) EDITAR PARCIAL ─────────────────────────────────────────────────────

test('editar empresa con PUT parcial (solo un campo) funciona', function () {
    planesBasicos();
    adminLogueado();

    $tenant = Tenant::factory()->create([
        'razon_social' => 'ORIGINAL SAC',
        'webhook_url' => 'https://original.com',
    ]);

    $response = $this->put("/admin/empresas/{$tenant->id}", [
        'webhook_url' => 'https://nuevo.com',
    ]);
    $response->assertRedirect();

    $tenant->refresh();
    expect($tenant->webhook_url)->toBe('https://nuevo.com')
        ->and($tenant->razon_social)->toBe('ORIGINAL SAC'); // no tocar el resto
});

// ── (4) REGENERAR CREDENCIALES ─────────────────────────────────────────────

test('regenerar credenciales cambia api_key/secret, invalida cache y expone para descarga', function () {
    planesBasicos();
    adminLogueado();
    Mail::fake();

    $tenant = Tenant::factory()->create([
        'emails' => ['destino@demo.com'],
        'api_key' => 'key_vieja_123',
        'api_secret' => 'sec_vieja_456',
    ]);

    // Simulo que el middleware cacheó la clave vieja
    Cache::put("tenant:key:{$tenant->api_key}", $tenant, 600);
    expect(Cache::has('tenant:key:key_vieja_123'))->toBeTrue();

    $response = $this->post("/admin/empresas/{$tenant->id}/regenerar-credenciales");
    $response->assertRedirect();

    $tenant->refresh();
    expect($tenant->api_key)->not->toBe('key_vieja_123')
        ->and($tenant->api_secret)->not->toBe('sec_vieja_456')
        ->and(strlen($tenant->api_key))->toBe(64)
        ->and(Cache::has('tenant:key:key_vieja_123'))->toBeFalse();

    // Credenciales para descarga (flash) + correo con .txt adjunto.
    $response->assertSessionHas('credenciales_nuevas');
    $cred = session('credenciales_nuevas');
    expect($cred['api_key'])->toBe($tenant->api_key)
        ->and($cred['api_secret'])->toBe($tenant->api_secret);

    Mail::assertQueued(TenantCredentialsMail::class, function ($mail) use ($tenant) {
        return $mail->tenant->id === $tenant->id
            && $mail->motivo === 'regeneracion'
            && count($mail->attachments()) === 1;
    });
});

// ── (4b) TOGGLE INVALIDA EL CACHE DE ResolveTenant ─────────────────────────

test('activar/desactivar empresa invalida el cache que usa la API', function () {
    planesBasicos();
    adminLogueado();

    $tenant = Tenant::factory()->create([
        'is_active' => true,
        'api_key' => 'key_toggle_test',
    ]);

    // Simulo el cache que deja ResolveTenant tras un request API
    Cache::put("tenant:key:{$tenant->api_key}", $tenant, 600);
    expect(Cache::has('tenant:key:key_toggle_test'))->toBeTrue();

    // Desactivar
    $this->post("/admin/empresas/{$tenant->id}/toggle")->assertRedirect();

    $tenant->refresh();
    expect($tenant->is_active)->toBeFalse()
        // El cache debe haberse limpiado para que la API vea el cambio ya
        ->and(Cache::has('tenant:key:key_toggle_test'))->toBeFalse();

    // Reactivar (mismo api_key) — vuelve a cachear y volver a limpiar
    Cache::put("tenant:key:{$tenant->api_key}", $tenant, 600);
    $this->post("/admin/empresas/{$tenant->id}/toggle")->assertRedirect();

    $tenant->refresh();
    expect($tenant->is_active)->toBeTrue()
        ->and(Cache::has('tenant:key:key_toggle_test'))->toBeFalse();
});

// ── (5) REGENERAR SIN DESTINATARIO SIGUE FUNCIONANDO ───────────────────────

test('regenerar sin emails ni usuario funciona igual (solo descarga, sin correo)', function () {
    planesBasicos();
    adminLogueado();
    Mail::fake();

    $tenant = Tenant::factory()->create([
        'user_id' => null,
        'emails' => null,
    ]);

    $response = $this->post("/admin/empresas/{$tenant->id}/regenerar-credenciales");
    $response->assertRedirect();
    $response->assertSessionHas('credenciales_nuevas');

    // Sin destinatario no hay correo — pero la operación no falla.
    Mail::assertNothingQueued();
});

// ── (6b) REPRODUCE BUG USUARIO: PUT con multipart/form-data ────────────────

test('editar con multipart/form-data (como Inertia forceFormData) persiste', function () {
    planesBasicos();
    adminLogueado();

    $tenant = Tenant::factory()->create([
        'nombre_comercial' => null,
        'webhook_url' => null,
    ]);

    // Simulamos EXACTAMENTE cómo Inertia envía forceFormData:
    // - POST real al servidor
    // - _method=PUT para method spoofing
    // - Content-Type: multipart/form-data
    $response = $this->call('POST', "/admin/empresas/{$tenant->id}", [
        '_method' => 'PUT',
        'ruc' => $tenant->ruc,
        'razon_social' => $tenant->razon_social,
        'nombre_comercial' => 'BUG REPRODUCIDO SAC',
        'sol_user' => 'MODDATOS',
        'environment' => 'beta',
        'tax_regime' => 'general',
        'plan' => 'pro',
        'max_documents_month' => 500,
        'webhook_url' => 'https://bug.example.com/hook',
        'is_active' => true,
    ], [], [], [
        'CONTENT_TYPE' => 'multipart/form-data',
    ]);

    $response->assertRedirect();

    $tenant->refresh();
    expect($tenant->nombre_comercial)->toBe('BUG REPRODUCIDO SAC')
        ->and($tenant->webhook_url)->toBe('https://bug.example.com/hook');
});

// ── (7) VALIDACIÓN: plan inválido ──────────────────────────────────────────

test('crear con plan inexistente falla con mensaje claro', function () {
    planesBasicos();
    adminLogueado();

    $response = $this->post('/admin/empresas', [
        'ruc' => '20512345679',
        'razon_social' => 'X',
        'sol_user' => 'MODDATOS',
        'sol_pass' => 'MODDATOS',
        'environment' => 'beta',
        'tax_regime' => 'general',
        'plan' => 'plan_inexistente',
        'max_documents_month' => 20,
    ]);

    $response->assertSessionHasErrors(['plan']);
});

// ── (7b) RECUPERACIÓN PÚBLICA por RUC → mail con token ─────────────────────

test('recuperar credenciales por RUC envía email con token', function () {
    planesBasicos();
    Mail::fake();

    $tenant = Tenant::factory()->create([
        'emails' => ['jefe@empresa.com'],
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/credenciales/recuperar', [
        'ruc' => $tenant->ruc,
    ]);

    $response->assertOk();

    Mail::assertQueued(CredentialRecoveryMail::class, function ($mail) use ($tenant) {
        return $mail->tenant->id === $tenant->id && strlen($mail->token) === 64;
    });
});

test('verificar token de recuperación devuelve nuevas credenciales', function () {
    planesBasicos();

    $tenant = Tenant::factory()->create([
        'emails' => ['jefe@empresa.com'],
        'is_active' => true,
    ]);
    $token = str_repeat('a', 64);
    Cache::put("credential_recovery:{$token}", $tenant->id, 600);

    $response = $this->postJson('/api/v1/credenciales/recuperar/verificar', [
        'token' => $token,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['estado', 'datos' => ['api_key', 'api_secret']])
        ->assertJsonPath('estado', 'exito');

    // Token consumido (uso único)
    expect(Cache::has("credential_recovery:{$token}"))->toBeFalse();
});

// ── (8) SIN AUTH ADMIN → 403 ───────────────────────────────────────────────

test('usuario no admin no puede crear empresa', function () {
    planesBasicos();
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->post('/admin/empresas', [
        'ruc' => '20512345678',
        'razon_social' => 'X',
    ]);

    // El middleware admin corta antes → 403 o redirect según cómo esté armado
    expect($response->status())->toBeIn([302, 403]);
    expect(Tenant::where('ruc', '20512345678')->exists())->toBeFalse();
});
