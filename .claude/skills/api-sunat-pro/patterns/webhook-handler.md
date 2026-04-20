# Webhook Handler — Recibir notificaciones de SUNAT

Recomendado para no hacer polling. La API dispara webhooks cuando SUNAT responde.

---

## Configurar

```http
PUT /api/v1/empresa
{
  "webhook_url": "https://tu-app.com/sunat-webhook"
}
```

Debe ser HTTPS accesible desde internet. Para dev local usa **ngrok** o **cloudflared**.

---

## Payload recibido

```http
POST https://tu-app.com/sunat-webhook
Content-Type: application/json

{
  "event": "document.sent" | "document.rejected",
  "tenant_id": 15,
  "model": "Invoice" | "Boleta" | "CreditNote" | "DebitNote" | "DispatchGuide" | "Summary" | "VoidedDocument" | "Retention" | "Perception",
  "id": 123,
  "data": {
    "numero": "F001-000123",
    "sunat_status": "aceptado" | "rechazado",
    "sunat_code": "0",
    "sunat_description": "La Factura ha sido aceptada",
    "hash_cpe": "suH+3qM1FC6czL9bmOBINy5mT1g="
  }
}
```

---

## Handler genérico

### Node.js / Express

```typescript
import express from 'express';

const app = express();
app.use(express.json());

app.post('/sunat-webhook', async (req, res) => {
  const { event, model, id, data } = req.body;

  try {
    switch (event) {
      case 'document.sent':
        await onDocumentSent(model, id, data);
        break;
      case 'document.rejected':
        await onDocumentRejected(model, id, data);
        break;
    }

    res.json({ ok: true });
  } catch (err) {
    console.error('Webhook error:', err);
    res.status(500).json({ error: 'internal' });    // la API reintentará
  }
});

async function onDocumentSent(model: string, id: number, data: any) {
  if (data.sunat_status === 'aceptado') {
    // Actualizar BD local
    await db.comprobante.update({
      where: { externalId: id, tipo: model },
      data: {
        estado: 'aceptado',
        hashCpe: data.hash_cpe,
        aceptadoEn: new Date(),
      },
    });

    // Opcional: email al cliente, notificación push, etc.
    await enviarEmailFacturaAceptada(id);
  }
}

async function onDocumentRejected(model: string, id: number, data: any) {
  await db.comprobante.update({
    where: { externalId: id, tipo: model },
    data: {
      estado: 'rechazado',
      errorCodigo: data.sunat_code,
      errorDescripcion: data.sunat_description,
    },
  });

  // Notificar al equipo (Slack, Discord, email)
  await slack.alert(`⚠️ ${model} ${data.numero} rechazado: ${data.sunat_description}`);
}
```

### PHP / Laravel

```php
// routes/api.php
Route::post('/sunat-webhook', [SunatWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth', 'verifyCsrfToken']);

// SunatWebhookController.php
public function handle(Request $request)
{
    $event = $request->input('event');
    $model = $request->input('model');
    $id = $request->input('id');
    $data = $request->input('data');

    try {
        match ($event) {
            'document.sent' => $this->onSent($model, $id, $data),
            'document.rejected' => $this->onRejected($model, $id, $data),
            default => Log::warning('Unknown webhook event', compact('event')),
        };

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        Log::error('Webhook error', ['ex' => $e->getMessage()]);
        return response()->json(['error' => 'internal'], 500);
    }
}
```

### Python / FastAPI

```python
from fastapi import FastAPI, Request, HTTPException

app = FastAPI()

@app.post('/sunat-webhook')
async def webhook(request: Request):
    payload = await request.json()

    event = payload['event']
    data = payload['data']

    try:
        if event == 'document.sent' and data['sunat_status'] == 'aceptado':
            await on_document_accepted(payload['id'], data)
        elif event == 'document.rejected':
            await on_document_rejected(payload['id'], data)

        return {'ok': True}
    except Exception as e:
        logger.error('Webhook error', exc_info=True)
        raise HTTPException(500, 'internal')
```

### Go

```go
func webhookHandler(w http.ResponseWriter, r *http.Request) {
    var payload struct {
        Event string                 `json:"event"`
        Model string                 `json:"model"`
        ID    int                    `json:"id"`
        Data  map[string]interface{} `json:"data"`
    }

    if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
        http.Error(w, "bad request", 400)
        return
    }

    switch payload.Event {
    case "document.sent":
        if payload.Data["sunat_status"] == "aceptado" {
            onAccepted(payload)
        }
    case "document.rejected":
        onRejected(payload)
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(map[string]bool{"ok": true})
}
```

---

## Reglas de oro

### 1. Responder rápido (< 5 segundos)

La API reintenta si tu endpoint tarda o falla. Para tareas pesadas:
1. Guarda el payload rápido (BD / queue)
2. Responde 200 OK inmediato
3. Procesa async en segundo plano

### 2. Idempotencia

Pueden llegar duplicados. Usa el `id` + `event` como clave única:

```sql
CREATE UNIQUE INDEX webhook_events_unique
ON webhook_events (model, external_id, event, received_at::date);
```

O simplemente:
```typescript
// Solo actualizar si el estado NO está ya en estado final
await db.comprobante.update({
  where: { id, estado: { not: 'aceptado' } },
  data: { estado: 'aceptado', hashCpe: data.hash_cpe },
});
```

### 3. Validar origen (opcional pero recomendado)

Agrega un `SUNAT_WEBHOOK_SECRET` compartido y valídalo:

```typescript
import crypto from 'crypto';

app.post('/sunat-webhook', (req, res) => {
  const signature = req.headers['x-webhook-signature'];
  const expected = crypto
    .createHmac('sha256', process.env.SUNAT_WEBHOOK_SECRET!)
    .update(JSON.stringify(req.body))
    .digest('hex');

  if (signature !== expected) {
    return res.status(401).json({ error: 'invalid signature' });
  }
  // ...
});
```

> **Nota**: la API PRO actual no firma webhooks por default. Si lo necesitas, pide la feature o valida por IP whitelist.

### 4. Log todo

```typescript
app.post('/sunat-webhook', async (req, res) => {
  await db.webhookLog.create({
    data: {
      payload: req.body,
      receivedAt: new Date(),
      status: 'processing',
    },
  });

  try {
    await processWebhook(req.body);
    // update status: processed
  } catch (e) {
    // update status: failed + error
  }

  res.json({ ok: true });
});
```

Si algo falla, puedes revisar y reprocesar manualmente.

### 5. Retries desde tu lado

Si tu handler crashea, la API reintentará (exponential backoff). Pero si el código es buggy, se va a loopear. Monitoréa:

```typescript
// Circuit breaker simple
if (recentFailures > 10 && recentWindow < 60s) {
  alertOncall('Webhook handler failing');
  return res.status(200).json({ ok: true }); // deja de reintentar
}
```

---

## Desarrollo local

Necesitas exponer tu localhost a internet para recibir webhooks:

```bash
# ngrok
ngrok http 3000
# → https://abc123.ngrok-free.app

# Configurar en la empresa
curl -X PUT $BASE_URL/empresa \
  -H "X-Api-Key: ..." -H "X-Api-Secret: ..." \
  -d '{"webhook_url":"https://abc123.ngrok-free.app/sunat-webhook"}'
```

Alternativa: **webhook.site** para solo inspeccionar el payload sin código.

---

## Eventos que NO se disparan (aún)

Para tu información — estos pasan en la API pero NO generan webhook actualmente:
- Cambio de `sunat_status` sin SUNAT event (ej. `pendiente` → `enviado`)
- Consultas SUNAT exitosas sin cambio de estado
- Descargas de PDF/XML por el cliente

Si necesitas alguno, polling es la alternativa.
