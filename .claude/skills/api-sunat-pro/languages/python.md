# Python — Integración

Stack: **Python 3.10+**, Django, FastAPI, Flask, scripts.

---

## 1. Cliente HTTP base

`sunat/client.py`:

```python
from __future__ import annotations
from dataclasses import dataclass
from typing import Any, Optional
import httpx


@dataclass
class SunatConfig:
    base_url: str
    api_key: str
    api_secret: str


class SunatApiError(Exception):
    def __init__(self, status: int, mensaje: str, codigo_error: Optional[str] = None):
        self.status = status
        self.mensaje = mensaje
        self.codigo_error = codigo_error
        super().__init__(mensaje)


class SunatValidationError(SunatApiError):
    def __init__(self, mensaje: str, errores: dict[str, list[str]]):
        self.errores = errores
        super().__init__(422, mensaje)


class SunatLimitError(SunatApiError):
    def __init__(self, mensaje: str, mejora_plan: Optional[dict] = None):
        self.mejora_plan = mejora_plan
        super().__init__(429, mensaje, 'limite_alcanzado')


class SunatClient:
    def __init__(self, config: SunatConfig, timeout: float = 30.0):
        self.config = config
        self.http = httpx.Client(
            base_url=config.base_url.rstrip('/'),
            timeout=timeout,
            headers={
                'Accept': 'application/json',
                'X-Api-Key': config.api_key,
                'X-Api-Secret': config.api_secret,
            },
        )

    def request(
        self,
        method: str,
        path: str,
        body: Optional[dict] = None,
        *,
        binary: bool = False,
    ) -> Any:
        kwargs: dict = {}
        if body is not None:
            kwargs['json'] = body

        response = self.http.request(method, path.lstrip('/'), **kwargs)

        if binary or 'application/json' not in response.headers.get('content-type', ''):
            if response.status_code >= 400:
                raise SunatApiError(response.status_code, f'Error HTTP {response.status_code}')
            return response.content

        data = response.json()

        if response.is_success and data.get('estado') == 'exito':
            return data.get('datos')

        mensaje = data.get('mensaje', f'Error HTTP {response.status_code}')
        if response.status_code == 422:
            raise SunatValidationError(mensaje, data.get('errores', {}))
        if response.status_code == 429:
            raise SunatLimitError(mensaje, data.get('mejora_plan'))
        raise SunatApiError(response.status_code, mensaje, data.get('codigo_error'))

    def get(self, path: str) -> Any:
        return self.request('GET', path)

    def post(self, path: str, body: dict) -> Any:
        return self.request('POST', path, body)

    def put(self, path: str, body: dict) -> Any:
        return self.request('PUT', path, body)

    def delete(self, path: str) -> Any:
        return self.request('DELETE', path)

    def close(self) -> None:
        self.http.close()

    def __enter__(self) -> 'SunatClient':
        return self

    def __exit__(self, *args) -> None:
        self.close()

    # Namespaces
    @property
    def facturas(self) -> 'FacturasResource':
        from sunat.resources.facturas import FacturasResource
        return FacturasResource(self)

    @property
    def boletas(self) -> 'BoletasResource':
        from sunat.resources.boletas import BoletasResource
        return BoletasResource(self)
```

---

## 2. Resources

`sunat/resources/facturas.py`:

```python
from __future__ import annotations
from typing import Optional
from sunat.client import SunatClient


class FacturasResource:
    def __init__(self, client: SunatClient):
        self.client = client

    def crear(self, data: dict) -> dict:
        return self.client.post('/facturas', data)

    def ver(self, id: int) -> dict:
        return self.client.get(f'/facturas/{id}')

    def listar(self, **params) -> dict:
        from urllib.parse import urlencode
        qs = urlencode({k: v for k, v in params.items() if v is not None})
        path = f'/facturas{"?" + qs if qs else ""}'
        return self.client.get(path)

    def actualizar(self, id: int, data: dict) -> dict:
        return self.client.put(f'/facturas/{id}', data)

    def enviar(self, id: int) -> dict:
        return self.client.post(f'/facturas/{id}/enviar', {})

    def pdf(self, id: int, formato: str = 'a4') -> bytes:
        return self.client.request('GET', f'/facturas/{id}/pdf?format={formato}', binary=True)

    def xml(self, id: int) -> bytes:
        return self.client.request('GET', f'/facturas/{id}/xml', binary=True)

    def cdr(self, id: int) -> bytes:
        return self.client.request('GET', f'/facturas/{id}/cdr', binary=True)
```

---

## 3. Pydantic models (opcional — tipado fuerte)

`sunat/models.py`:

```python
from pydantic import BaseModel, Field
from typing import Optional, Literal
from decimal import Decimal


class Cliente(BaseModel):
    tipo_doc: Literal['0', '1', '4', '6', '7', 'A']
    num_doc: str
    razon_social: str
    direccion: Optional[str] = None
    email: Optional[str] = None
    telefono: Optional[str] = None


class Item(BaseModel):
    codigo: str
    descripcion: str
    unidad: str = 'NIU'
    cantidad: Decimal
    precio_unitario: Decimal
    tip_afe_igv: Literal['10', '11', '12', '20', '30', '40'] = '10'
    descuento: Optional[Decimal] = None


class CrearFacturaInput(BaseModel):
    serie: str
    fecha_emision: str
    fecha_vencimiento: Optional[str] = None
    tipo_operacion: str = '0101'
    tipo_moneda: Literal['PEN', 'USD'] = 'PEN'
    forma_pago: Literal['Contado', 'Credito'] = 'Contado'
    cliente: Cliente
    items: list[Item] = Field(min_length=1)
    enviar_automatico: bool = True
    observacion: Optional[str] = None


class SunatEstado(BaseModel):
    estado: Literal['pendiente', 'enviado', 'aceptado', 'rechazado', 'anulado']
    codigo: Optional[str] = None
    descripcion: Optional[str] = None
    hash_cpe: Optional[str] = None
```

---

## 4. FastAPI integration

`app/main.py`:

```python
from fastapi import FastAPI, HTTPException, Depends
from sunat.client import SunatClient, SunatConfig, SunatValidationError
import os

app = FastAPI()

def get_sunat() -> SunatClient:
    return SunatClient(SunatConfig(
        base_url=os.environ['SUNAT_BASE_URL'],
        api_key=os.environ['SUNAT_API_KEY'],
        api_secret=os.environ['SUNAT_API_SECRET'],
    ))


@app.post('/facturas')
def crear_factura(data: dict, sunat: SunatClient = Depends(get_sunat)):
    try:
        return sunat.facturas.crear(data)
    except SunatValidationError as e:
        raise HTTPException(422, detail={'errores': e.errores})
    except Exception as e:
        raise HTTPException(500, detail=str(e))
```

---

## 5. Django integration

`settings.py`:
```python
SUNAT_BASE_URL = os.environ['SUNAT_BASE_URL']
SUNAT_API_KEY = os.environ['SUNAT_API_KEY']
SUNAT_API_SECRET = os.environ['SUNAT_API_SECRET']
```

`sunat/django_utils.py`:
```python
from django.conf import settings
from functools import lru_cache
from sunat.client import SunatClient, SunatConfig

@lru_cache(maxsize=1)
def get_sunat_client() -> SunatClient:
    return SunatClient(SunatConfig(
        base_url=settings.SUNAT_BASE_URL,
        api_key=settings.SUNAT_API_KEY,
        api_secret=settings.SUNAT_API_SECRET,
    ))
```

`views.py`:
```python
from django.http import JsonResponse
from sunat.django_utils import get_sunat_client
from sunat.client import SunatValidationError

def emitir_factura(request):
    try:
        factura = get_sunat_client().facturas.crear(json.loads(request.body))
        return JsonResponse({'ok': True, 'factura': factura}, status=201)
    except SunatValidationError as e:
        return JsonResponse({'ok': False, 'errores': e.errores}, status=422)
```

---

## 6. Webhook handler (FastAPI)

```python
from fastapi import FastAPI, Request

@app.post('/sunat/webhook')
async def sunat_webhook(request: Request):
    payload = await request.json()

    if payload['event'] == 'document.sent':
        if payload['data']['sunat_status'] == 'aceptado':
            # actualizar BD, enviar email, etc.
            ...
    elif payload['event'] == 'document.rejected':
        # notificar equipo
        ...

    return {'ok': True}
```

---

## 7. Testing (pytest)

```python
import pytest
from unittest.mock import patch
from sunat.client import SunatClient, SunatConfig, SunatValidationError
import httpx


@pytest.fixture
def client():
    return SunatClient(SunatConfig('http://mock', 'key', 'secret'))


def test_crea_factura(client, respx_mock):
    respx_mock.post('http://mock/facturas').mock(
        return_value=httpx.Response(201, json={
            'estado': 'exito',
            'mensaje': 'Creado',
            'datos': {'id': 1, 'numero_completo': 'F001-000001'},
        })
    )

    factura = client.facturas.crear({
        'serie': 'F001',
        'fecha_emision': '2026-04-19',
        'cliente': {'tipo_doc': '6', 'num_doc': '20000000001', 'razon_social': 'TEST'},
        'items': [{'codigo': 'P', 'descripcion': 'X', 'unidad': 'NIU',
                  'cantidad': 1, 'precio_unitario': 100, 'tip_afe_igv': '10'}],
    })

    assert factura['id'] == 1


def test_raises_validation_error(client, respx_mock):
    respx_mock.post('http://mock/facturas').mock(
        return_value=httpx.Response(422, json={
            'estado': 'error',
            'mensaje': 'Error de validación',
            'errores': {'serie': ['El campo serie es obligatorio.']},
        })
    )

    with pytest.raises(SunatValidationError) as exc:
        client.facturas.crear({})
    assert 'serie' in exc.value.errores
```

---

## 8. Estructura

```
sunat/
├── __init__.py
├── client.py                 # SunatClient + errores
├── models.py                 # Pydantic models
├── resources/
│   ├── __init__.py
│   ├── facturas.py
│   ├── boletas.py
│   ├── notas.py
│   └── empresa.py
└── django_utils.py            # si Django
```

---

## 9. `.env`

```
SUNAT_BASE_URL=https://api.kodevo.es/sunat-api/api/v1
SUNAT_API_KEY=xxx
SUNAT_API_SECRET=yyy
```

Carga con `python-dotenv` o `pydantic-settings`.

---

## 10. Dependencias

```bash
pip install httpx pydantic
# dev
pip install pytest respx
```
