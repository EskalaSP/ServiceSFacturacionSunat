# Candado de licencia — diseño y ofuscación

Cómo está protegido este sistema contra reventa, y cómo **blindar** el candado
antes de entregar una copia a un cliente.

## Cómo funciona

Este sistema valida su licencia contra el servidor del proveedor
(`LICENSE_SERVER_URL`, por defecto `https://agenda.kodevo.es`) antes de emitir a
SUNAT en **producción**. En **beta** no se valida (para poder probar).

Hay **dos barreras independientes** (defensa en profundidad):

1. **Middleware `license`** (`app/Http/Middleware/CheckLicense.php`) — corre en
   las 12 rutas de emisión SUNAT. Devuelve 403 con mensaje claro si no hay
   licencia válida.
2. **Guard en el envío** (`GreenterService::guardEmisionAutorizada()`) — corre
   en el corazón del firmado/envío a SUNAT (`send()` y `sendDespatch()`).
   Aunque alguien **borre el middleware**, la emisión a producción no ocurre
   sin licencia.

Además, el interruptor `LICENSE_ENABLED=false` **solo funciona en `APP_ENV=local`**.
En un servidor real (production) se ignora: no se puede apagar el candado por env.

La identidad es por **instalación** (`machine_id`, persistido en
`storage/app/license/machine-id`), así funciona igual en local, VPS u hosting.

## El límite honesto

Como se vende **código fuente en PHP**, un comprador decidido puede editar los
archivos y quitar ambas barreras. El candado en texto plano frena al cliente
honesto, **no al pirata**. Para que sea un candado real hay que **ofuscar los
archivos críticos** con un encoder de pago (ionCube o SourceGuardian), de modo
que no se puedan leer ni editar. El resto del código queda legible.

## Archivos críticos a ofuscar

Encripta **solo estos** (son el candado; el resto del sistema no hace falta):

```
app/Services/License/LicenseClient.php
app/Services/License/LicenseCheck.php
app/Services/License/MachineId.php
app/Http/Middleware/CheckLicense.php
app/Services/Greenter/GreenterService.php   # contiene el guard de envío
```

> Nota: `GreenterService.php` también hace el firmado/envío real, así que
> ofuscarlo protege el guard sin romper la funcionalidad.

## Opción A — ionCube Encoder

Requiere licencia del **ionCube Encoder** (tú) y el **ionCube Loader** instalado
en el servidor del cliente (la mayoría de hostings PHP ya lo traen).

```bash
ioncube_encoder \
  app/Services/License/LicenseClient.php \
  app/Services/License/LicenseCheck.php \
  app/Services/License/MachineId.php \
  app/Http/Middleware/CheckLicense.php \
  app/Services/Greenter/GreenterService.php \
  -o encoded/ \
  --replace-target \
  --optimize max
```

Copia los archivos `encoded/` sobre los originales al empaquetar la venta.

## Opción B — SourceGuardian

Requiere licencia de **SourceGuardian** (tú) y su Loader en el servidor cliente.

```bash
sourceguardian \
  --encode \
  --php 8.3 \
  -o ./ \
  app/Services/License/LicenseClient.php \
  app/Services/License/LicenseCheck.php \
  app/Services/License/MachineId.php \
  app/Http/Middleware/CheckLicense.php \
  app/Services/Greenter/GreenterService.php
```

## Recomendado además

- **Fingerprint por copia:** antes de empaquetar cada venta, incrusta el
  `fingerprint` de la licencia (el que genera el panel) en varios sitios del
  código, para rastrear quién filtró una copia.
- **Contrato de licencia** firmado (RUC/DNI del comprador) antes de entregar.
- **Actualizaciones por Composer privado** con token por cliente, para que una
  copia pirata no reciba parches normativos SUNAT.

## Verificar tras entregar

En la instalación del cliente:

```bash
php artisan license:status     # muestra la instalación y si la licencia es válida
php artisan license:activate   # registra esta instalación en el servidor
```
