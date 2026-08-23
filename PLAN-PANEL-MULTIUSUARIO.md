# Plan — Panel multiusuario con roles y emisión completa

> Documento de diseño (para revisión antes de codificar).
> Proyecto: **API-PRO** (Laravel 12 + Fortify + Inertia 2 + React).
> Objetivo: que el panel administre **todos** los tipos de comprobante de la API, con un
> control de acceso de **3 niveles** (super admin / dueño / cajero), donde cada quien opera
> solo lo que le corresponde y el dueño puede crear ayudantes para **su** empresa.

---

## 1. Objetivo y alcance

- El **super admin** (tú) controla **todas** las empresas: emitir, anular, reenviar, ver y
  editar cualquier comprobante de cualquier empresa.
- El **dueño** (cliente que compra acceso) controla **solo su empresa**: emite, anula,
  reenvía, edita sus datos, y **crea sub-usuarios (cajeros)** para su propia empresa.
- El **cajero/ayudante** hace **solo lo que el dueño le habilite**, y solo en esa empresa.
- El panel debe cubrir **todos los tipos de comprobante** que hoy existen únicamente en la
  API (boletas, guías, resúmenes, retenciones, percepciones, etc.), no solo factura/nota.
- **Sin módulos de catálogo** (categorías, inventario). El detalle de la venta se llena
  **manualmente** (el JSON de emisión), igual que ya funciona en facturas.

**Fuera de alcance (por ahora):** pasarela de pago del cliente final, catálogo de
productos/stock, app móvil.

---

## 2. Roles y modelo de datos

### 2.1 Tres niveles

| Nivel | Rol global (`users.role`) | Alcance | Origen del poder |
|---|---|---|---|
| Super admin | `super_admin` | Todas las empresas | Rol global (bypass total) |
| Dueño | `cliente` | Su(s) empresa(s) | Fila pivote con `role = owner` |
| Cajero | `cliente` | Una empresa | Fila pivote con `role = cajero` + permisos granulares |

- Los roles internos actuales (`admin`, `soporte`, `lectura`) **se conservan** para tu
  equipo interno. El nuevo valor `cliente` marca a los usuarios de empresas y **no** tiene
  acceso al panel `admin/` (`hasPanelAccess()` sigue devolviendo `false` para `cliente`).
- `Tenant.user_id` se conserva como “dueño principal / creador”; la autorización real se
  lee del **pivote**.

### 2.2 Nueva tabla pivote `tenant_user`

```
tenant_user
├─ id
├─ tenant_id      FK → tenants (cascade)
├─ user_id        FK → users   (cascade)
├─ role           enum('owner','cajero')
├─ abilities      json         # lista de permisos (solo aplica a cajero; owner = todo)
├─ is_active      boolean  default true
├─ created_at / updated_at
└─ UNIQUE (tenant_id, user_id)
```

- **owner**: permisos totales sobre su empresa; `abilities` se ignora.
- **cajero**: solo puede lo que esté listado en `abilities` (ver catálogo §3).
- Un usuario puede tener varias filas (pertenecer a más de una empresa, con rol distinto
  en cada una). Ej.: dueño de la empresa A y cajero en la empresa B.

### 2.3 Relaciones (Eloquent)

```php
// User
public function tenants(): BelongsToMany
    => belongsToMany(Tenant::class)->withPivot('role','abilities','is_active')->withTimestamps();

public function membershipFor(Tenant $t): ?Pivot;      // helper
public function puede(string $ability, Tenant $t): bool; // envuelve el Gate

// Tenant
public function miembros(): BelongsToMany  // inverso
    => belongsToMany(User::class)->withPivot(...);
```

> `Tenant.user_id` (owner principal) se mantiene; al crear una empresa se inserta también
> su fila pivote `owner` para unificar la lectura de permisos.

---

## 3. Catálogo de permisos (granular)

Cada permiso es una cadena `"{recurso}.{acción}"`. El dueño marca, por cada cajero, qué
permisos tiene (checkboxes). El **owner** los tiene todos implícitamente; el **super admin**
hace bypass de todo.

### 3.1 Por tipo de comprobante

Acciones posibles por tipo: `emitir`, `editar`, `anular`, `reenviar`, `descargar`.

| Recurso (tipo) | clave base |
|---|---|
| Factura (01) | `factura` |
| Boleta (03) | `boleta` |
| Nota de Crédito (07) | `nota_credito` |
| Nota de Débito (08) | `nota_debito` |
| Guía Remisión Remitente (09) | `guia_remitente` |
| Guía Remisión Transportista (31) | `guia_transportista` |
| Resumen Diario | `resumen` |
| Comunicación de Baja / Anulación | `anulacion` |
| Retención (20) | `retencion` |
| Percepción (40) | `percepcion` |
| Reversión (RR) | `reversion` |
| Cotización (interno) | `cotizacion` |
| Nota de Venta (interno) | `nota_venta` |

Ejemplo de `abilities` de un cajero que solo emite factura y boleta:
```json
["factura.emitir","factura.descargar","boleta.emitir","boleta.descargar","cliente.gestionar"]
```

### 3.2 Módulos transversales (por empresa)

| Permiso | Qué habilita |
|---|---|
| `cliente.gestionar` | Crear/editar clientes (receptores) |
| `serie.gestionar` | Ver/crear series y correlativos |
| `sucursal.gestionar` | Ver/crear sucursales |
| `reporte.ver` | Reportes (ventas, cobranzas, etc.) |
| `exportar` | Descargar ZIP masivo |
| `consulta.cpe` | Consultar CPE/CDR en SUNAT |
| `config.editar` | Editar datos de la empresa, certificado, clave SOL |
| `apikey.ver` | Ver/regenerar credenciales de API |
| `equipo.gestionar` | Crear/gestionar sub-usuarios (**solo owner**) |
| `sire.gestionar` | Módulo SIRE (RCE, ajustes, tickets) |

**Presets sugeridos para un cajero nuevo** (editable):
`*.emitir`, `*.descargar`, `*.reenviar` de los comprobantes de venta comunes +
`cliente.gestionar`. **Apagados por defecto:** `*.anular`, `config.editar`, `apikey.ver`,
`equipo.gestionar`, `sire.gestionar`.

---

## 4. Matriz de permisos por rol

| Acción | Super admin | Dueño (owner) | Cajero |
|---|:--:|:--:|:--:|
| Emitir cualquier tipo | ✅ todas las empresas | ✅ su empresa | ⚙️ según `abilities` |
| Anular / comunicación de baja | ✅ | ✅ | ⚙️ opcional |
| Reenviar a SUNAT | ✅ | ✅ | ⚙️ opcional |
| Descargar PDF/XML/CDR | ✅ | ✅ | ⚙️ opcional |
| Ver historial / consultas | ✅ (todas) | ✅ (suya) | ✅ (suya) |
| Gestionar clientes | ✅ | ✅ | ⚙️ opcional |
| Series / sucursales | ✅ | ✅ | ⚙️ opcional |
| Reportes / exportar | ✅ | ✅ | ⚙️ opcional |
| Editar empresa (cert, SOL) | ✅ | ✅ | ❌ |
| Ver/regenerar API key | ✅ | ✅ | ❌ (salvo que se habilite) |
| **Crear/gestionar usuarios** | ✅ (todos) | ✅ (solo su empresa) | ❌ |
| Panel `admin/` (cross-empresa) | ✅ | ❌ | ❌ |

`⚙️` = lo decide el dueño por cajero (checkbox granular).

---

## 5. Cobertura de comprobantes (estado actual → objetivo)

Lógica ya implementada en `Api\V1\*Controller` + `Actions`. El panel **reutiliza** esas
Actions vía controladores web delgados (patrón ya usado por `Web\Sunat\FacturaController`).

### 5.1 Comprobantes SUNAT

| Tipo | API | Form panel | Trabajo |
|---|:--:|:--:|---|
| Factura (01) | ✅ | ✅ | reutilizar |
| Boleta (03) | ✅ | ❌ | **nuevo form + controlador web** |
| Nota Crédito (07) | ✅ | ✅ | reutilizar |
| Nota Débito (08) | ✅ | ✅ | reutilizar |
| Guía Remisión Remitente (09) | ✅ | ❌ | **nuevo (form complejo: traslado, vehículo, chofer)** |
| Guía Remisión Transportista (31) | ✅ | ❌ | **nuevo** |
| Resumen Diario (boletas) | ✅ | ❌ | **nuevo (selección de boletas del día)** |
| Comunicación de Baja / Anulación | ✅ | ⚠️ solo admin | **flujo en panel de cliente** |
| Retención (20) | ✅ | ❌ | **nuevo form** |
| Percepción (40) | ✅ | ❌ | **nuevo form** |
| Reversión (RR) | ✅ | ❌ | **nuevo (anula retención/percepción)** |

### 5.2 Internos / utilidades

| Tipo | API | Panel | Trabajo |
|---|:--:|:--:|---|
| Cotización | ✅ | ✅ | reutilizar |
| Nota de Venta | ✅ | ❌ | **nuevo form** |
| Consultar CPE / CDR | ✅ | ❌ | **nueva pantalla de consulta** |
| Reportes (7) | ✅ | ❌ | **nuevas vistas de reporte** |
| Exportar ZIP | ✅ | ❌ | **botón/acción** |
| Series / Sucursales (panel cliente) | ✅ | ⚠️ solo admin | **exponer al dueño** |
| Clientes / Config / API key | ✅ | ✅ | reutilizar |
| Pagos (facturas/boletas/nota venta) | ✅ | parcial | revisar |
| **SIRE** (RCE, ajustes, tickets, reconciliación) | ✅ | ❌ | **módulo aparte (grande)** |

---

## 6. Arquitectura técnica

### 6.1 Empresa activa (multi-empresa)
- Hoy el panel toma `auth()->user()->tenants()->first()`. Se reemplaza por una **empresa
  activa** resuelta del pivote y guardada en sesión (`empresa_activa_id`).
- **Selector de empresa** en el header cuando el usuario pertenece a más de una.
- El super admin puede fijar cualquier empresa como activa (“entrar como empresa X”).
- Helper central `ResolverEmpresaActiva` (service) → único punto de verdad; nada de
  `->first()` disperso.

### 6.2 Autorización
- **`Gate::before`**: si `super_admin` → permitir todo.
- **Gates con contexto** en `AppServiceProvider`: `emitir`, `anular`, `reenviar`,
  `descargar`, `editar-empresa`, `gestionar-usuarios`, etc. Cada uno recibe
  `(User $u, Tenant $t, string $tipo = null)` y consulta el pivote:
  ```php
  $m = $u->membershipFor($t);
  if (!$m || !$m->is_active) return false;
  if ($m->role === 'owner') return true;
  return in_array("{$tipo}.{$accion}", $m->abilities ?? []);
  ```
- **Middleware `tenant.member`**: garantiza que el usuario pertenece a la empresa activa
  (evita fugas cross-empresa; recuerda que **no hay Global Scope de tenant**, cada query
  filtra por `tenant_id`).
- **No se toca** el middleware `license` ni `GuardEmisionAutorizada` — la licencia sigue
  blindando la emisión a producción por encima de todo esto.

### 6.3 Reuso de lógica
- Cada tipo nuevo = una página Inertia (`resources/js/pages/sunat/{tipo}/`) + un
  controlador `Web\Sunat\{Tipo}Controller` que valida con el mismo FormRequest y llama a la
  **misma Action/Controller de la API**. Cero duplicación de reglas SUNAT.
- El panel `admin/` extiende `EmpresaComprobanteController` para **crear** cualquier tipo
  (hoy solo ve / reenvía / anula / descarga).

---

## 7. Restricciones y seguridad

- **SMTP bloqueado en el VPS**: las invitaciones de cajero **no** van por correo. El dueño
  crea la cuenta con **contraseña temporal mostrada una sola vez** (o link de establecer
  contraseña); el cajero la cambia al primer ingreso. Mismo patrón que el secreto de licencia.
- **Aislamiento multi-tenant**: toda query nueva filtra explícitamente por la empresa
  activa; el middleware `tenant.member` es la segunda barrera.
- **Escalada de privilegios**: un dueño **no** puede crear otro `owner` ni tocar otras
  empresas; `equipo.gestionar` solo permite crear `cajero` en **su** empresa. Validado en
  servidor, no solo en UI.
- **Cajero desactivado** (`is_active=false` en pivote) pierde acceso de inmediato.

---

## 8. Fases y checklist

### Fase 1 — Base RBAC *(cimiento)*
- [ ] Migración `tenant_user` (pivote + `role` + `abilities` + `is_active`).
- [ ] Backfill: por cada `Tenant.user_id` existente, crear fila pivote `owner`.
- [ ] Enum `MembershipRole` (`owner`/`cajero`) + catálogo de `abilities` (constantes).
- [ ] Relaciones `User↔Tenant` (belongsToMany) + helpers `membershipFor`, `puede`.
- [ ] Valor de rol global `cliente` en `User` (sin acceso a `admin/`).
- [ ] `ResolverEmpresaActiva` (sesión + selector) reemplazando `->tenants()->first()`.
- [ ] Gates con contexto + `Gate::before` super_admin.
- [ ] Middleware `tenant.member`.
- [ ] Tests: aislamiento cross-empresa, bypass super_admin, cajero sin permiso → 403.

### Fase 2 — Comprobantes de uso diario
- [ ] Boleta (form + `Web\Sunat\BoletaController` → `CreateBoletaAction`).
- [ ] Guía Remisión Remitente (form traslado/vehículo/chofer).
- [ ] Guía Remisión Transportista.
- [ ] Resumen Diario (selección de boletas del día → `SummaryController`).
- [ ] Anulación / comunicación de baja desde panel de cliente.

### Fase 3 — Especiales
- [ ] Retención (20), Percepción (40), Reversión (RR), Nota de Venta.

### Fase 4 — Consultas y reportes
- [ ] Consultar CPE/CDR, Reportes (7), Exportar ZIP.
- [ ] Series y sucursales para el dueño (no solo admin).

### Fase 5 — SIRE
- [ ] RCE (propuesta, resumen, aceptar/registrar), ajustes posteriores, tickets,
      reconciliación. Módulo grande, va al final.

### Fase 6 — UX por rol
- [ ] Menús recortados por rol/permisos (cajero ve solo lo habilitado).
- [ ] Selector de empresa activa.
- [ ] “Mi equipo”: el dueño crea/edita/desactiva cajeros con checkboxes de permisos.
- [ ] Pantalla de permisos granular por cajero.

> Cada fase se despliega sola sin romper lo anterior. Cada tipo de comprobante es un
> incremento pequeño y testeable (reusa Action existente).

---

## 9. Decisiones tomadas

- **Modelo:** varios usuarios por empresa (pivote `tenant_user`), no 1:1.
- **Permisos del cajero:** **granulares por tipo** (checkboxes que marca el dueño).
- **Jerarquía:** super admin (todo) › dueño (su empresa + crea cajeros) › cajero (lo permitido).
- **Cobertura:** el panel debe emitir/gestionar **todos** los tipos de la API.
- **Sin catálogo:** detalle de venta manual.
- **Entrega:** primero este documento; luego Fase 1.

## 10. Preguntas abiertas (para confirmar antes de Fase 2+)

1. ¿El **cajero** puede pertenecer a más de una empresa? (el pivote lo permite; default: sí).
2. ¿Límite de sub-usuarios por empresa según plan? (ej. plan free = 1 cajero).
3. Guías de remisión: ¿las necesitas en Fase 2 o pueden ir más tarde por su complejidad?
4. SIRE: ¿es prioritario o puede quedar como última fase?
