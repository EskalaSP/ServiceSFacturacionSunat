# Credenciales: API Key y API Secret

Guía para el programador que integra la API o gestiona cuentas de clientes. Explica cómo funcionan las credenciales, cómo recuperarlas si se pierden y qué hacer en cada escenario.

---

## Cómo funcionan las credenciales

Cada empresa registrada recibe dos credenciales que se envían en el header de cada request:

| Header | Descripción |
|---|---|
| `X-Api-Key` | Identificador público de la empresa. Se puede ver en cualquier momento. |
| `X-Api-Secret` | Contraseña secreta. Se muestra **una sola vez** al registrarse o regenerar. |

**El `api_secret` se guarda como hash SHA256** en la base de datos. No existe forma de recuperar el valor original — solo se puede generar uno nuevo. Este diseño es intencional: ni el equipo técnico puede ver el secret de un cliente.

---

## Aviso importante al registrar un cliente nuevo

Cuando un cliente se registra, el sistema devuelve el `api_secret` en texto plano **una única vez**. Muestra este aviso en tu interfaz:

```
⚠️ Guarda tu api_secret ahora.
   No podrás verlo de nuevo.
   Si lo pierdes, deberás regenerar tus credenciales
   (las anteriores quedarán inválidas).
```

---

## Consultar credenciales actuales

Si el cliente aún tiene acceso a la API (recuerda al menos una de sus credenciales válidas):

**Request:**
```http
GET /api/v1/empresa/credenciales
X-Api-Key: tu_api_key
X-Api-Secret: tu_api_secret
```

**Response:**
```json
{
    "estado": "exito",
    "datos": {
        "api_key": "g5n4guFwe6m36CyssfonVnvPiTxquWBVAx7MRs6GGJczH3hG8TVP2ddYM1c1OPuA",
        "api_secret": "*** oculto — use POST /empresa/credenciales/regenerar para obtener uno nuevo ***"
    },
    "mensaje": "El api_secret no puede mostrarse. Si lo perdiste, regenera tus credenciales."
}
```

Útil para confirmar el `api_key` actual sin exponer el secret.

---

## Escenario 1 — Recuerdas ambas credenciales pero quieres regenerar

Por ejemplo, si sospechas que el secret fue comprometido:

**Request:**
```http
POST /api/v1/empresa/credenciales/regenerar
X-Api-Key: tu_api_key
X-Api-Secret: tu_api_secret
```

**Response:**
```json
{
    "estado": "exito",
    "mensaje": "Credenciales regeneradas exitosamente.",
    "datos": {
        "api_key": "nuevo_api_key_aqui",
        "api_secret": "nuevo_api_secret_aqui_en_texto_plano",
        "aviso": "Guarda estas credenciales ahora. El api_secret NO se volverá a mostrar."
    }
}
```

> Las credenciales anteriores quedan inválidas inmediatamente. Actualiza tu integración antes de hacer más requests.

---

## Escenario 2 — Perdiste solo el `api_secret`

El `api_key` sigue siendo válido, pero sin el secret no puedes autenticarte. Usa el flujo de recuperación por email (Escenario 3) o, si tienes acceso físico al servidor, regenera directamente desde Artisan:

```bash
php artisan tinker
>>> $tenant = \App\Models\Tenant::where('ruc', '20512345678')->first();
>>> $secret = \Illuminate\Support\Str::random(64);
>>> $tenant->update(['api_secret' => hash('sha256', $secret)]);
>>> echo $secret; // ← guardar este valor
```

---

## Escenario 3 — Perdiste AMBAS credenciales (el peor caso)

Flujo de recuperación en 2 pasos usando el RUC o email registrado.

### Paso 1 — Solicitar recuperación

```http
POST /api/v1/credenciales/recuperar
Content-Type: application/json
```

**Con RUC:**
```json
{ "ruc": "20512345678" }
```

**Con email:**
```json
{ "email": "contacto@miempresa.com" }
```

**Response (siempre igual, no revela si existe o no):**
```json
{
    "estado": "exito",
    "mensaje": "Si el RUC o email está registrado, recibirás las instrucciones en breve."
}
```

Se envía un email al correo registrado de la empresa con un token de un solo uso, válido por **30 minutos**.

> **Rate limit:** máximo 3 solicitudes por IP cada 10 minutos.

---

### Paso 2 — Verificar token y recibir credenciales nuevas

Copia el token que llegó al email:

```http
POST /api/v1/credenciales/recuperar/verificar
Content-Type: application/json

{
    "token": "el_token_de_64_caracteres_del_email"
}
```

**Response exitoso:**
```json
{
    "estado": "exito",
    "mensaje": "Credenciales regeneradas exitosamente.",
    "datos": {
        "api_key": "nuevo_api_key",
        "api_secret": "nuevo_api_secret_en_texto_plano",
        "aviso": "Guarda estas credenciales ahora. El api_secret NO se volverá a mostrar."
    }
}
```

**Response si el token venció o ya fue usado:**
```json
{
    "estado": "error",
    "mensaje": "Token inválido o expirado."
}
```

> El token es de **un solo uso**: se elimina al verificarse correctamente. Si el token venció, repite el Paso 1.

---

## Tabla resumen de escenarios

| Situación | Solución |
|---|---|
| Quiero ver mi api_key | `GET /api/v1/empresa/credenciales` |
| Perdí el api_secret (tengo el api_key) | `POST /api/v1/credenciales/recuperar` con RUC o email |
| Quiero rotar credenciales por seguridad | `POST /api/v1/empresa/credenciales/regenerar` |
| Perdí ambas credenciales | `POST /api/v1/credenciales/recuperar` → verificar token del email |
| No tengo email registrado | Contactar soporte técnico para regeneración manual vía Artisan |

---

## Seguridad: qué ocurre al regenerar

- Las credenciales anteriores quedan **inválidas de inmediato**.
- Se limpia el caché de autenticación (`tenant:key:{api_key}`).
- El nuevo `api_secret` se muestra **una sola vez** en el response.
- En la base de datos solo se guarda `hash('sha256', $api_secret)` — nunca el valor en texto plano.

---

## Archivos relevantes

| Archivo | Propósito |
|---|---|
| `app/Http/Controllers/Api/V1/CredentialRecoveryController.php` | Flujo de recuperación por email (solicitar + verificar) |
| `app/Http/Controllers/Api/V1/TenantController.php` | Ver credenciales actuales + regenerar con acceso |
| `app/Mail/CredentialRecoveryMail.php` | Email con el token de recuperación |
