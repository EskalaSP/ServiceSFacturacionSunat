# Go — Integración

Stack: **Go 1.22+**, net/http, chi, fiber, gin, standard library.

---

## 1. Cliente HTTP base

`sunat/client.go`:

```go
package sunat

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

type Config struct {
	BaseURL   string
	APIKey    string
	APISecret string
	Timeout   time.Duration
}

type Client struct {
	cfg  Config
	http *http.Client

	Facturas *FacturasResource
	Boletas  *BoletasResource
	Clientes *ClientesResource
	Empresa  *EmpresaResource
}

func New(cfg Config) *Client {
	if cfg.Timeout == 0 {
		cfg.Timeout = 30 * time.Second
	}
	c := &Client{
		cfg:  cfg,
		http: &http.Client{Timeout: cfg.Timeout},
	}
	c.Facturas = &FacturasResource{client: c}
	c.Boletas = &BoletasResource{client: c}
	c.Clientes = &ClientesResource{client: c}
	c.Empresa = &EmpresaResource{client: c}
	return c
}

// Response wrapper estándar
type Response[T any] struct {
	Estado       string              `json:"estado"`
	Mensaje      string              `json:"mensaje"`
	Datos        T                   `json:"datos,omitempty"`
	Meta         map[string]any      `json:"meta,omitempty"`
	Errores      map[string][]string `json:"errores,omitempty"`
	CodigoError  string              `json:"codigo_error,omitempty"`
}

// Errores tipados
type APIError struct {
	Status      int
	Mensaje     string
	CodigoError string
}

func (e *APIError) Error() string { return e.Mensaje }

type ValidationError struct {
	APIError
	Errores map[string][]string
}

type LimitError struct {
	APIError
	MejoraPlan map[string]any
}

// Request low-level
func (c *Client) Request(method, path string, body any, binary bool) (io.ReadCloser, error) {
	var reader io.Reader
	if body != nil {
		b, err := json.Marshal(body)
		if err != nil {
			return nil, err
		}
		reader = bytes.NewReader(b)
	}

	url := strings.TrimRight(c.cfg.BaseURL, "/") + "/" + strings.TrimLeft(path, "/")
	req, err := http.NewRequest(method, url, reader)
	if err != nil {
		return nil, err
	}

	req.Header.Set("Accept", "application/json")
	req.Header.Set("X-Api-Key", c.cfg.APIKey)
	req.Header.Set("X-Api-Secret", c.cfg.APISecret)
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	resp, err := c.http.Do(req)
	if err != nil {
		return nil, err
	}

	if binary || !strings.Contains(resp.Header.Get("Content-Type"), "application/json") {
		if resp.StatusCode >= 400 {
			resp.Body.Close()
			return nil, &APIError{Status: resp.StatusCode, Mensaje: fmt.Sprintf("HTTP %d", resp.StatusCode)}
		}
		return resp.Body, nil
	}

	rawBody, _ := io.ReadAll(resp.Body)
	resp.Body.Close()

	// Parse genérico primero para inspect
	var generic map[string]any
	_ = json.Unmarshal(rawBody, &generic)

	if resp.StatusCode < 400 && generic["estado"] == "exito" {
		return io.NopCloser(bytes.NewReader(rawBody)), nil
	}

	mensaje, _ := generic["mensaje"].(string)
	if mensaje == "" {
		mensaje = fmt.Sprintf("HTTP %d", resp.StatusCode)
	}
	codError, _ := generic["codigo_error"].(string)

	switch resp.StatusCode {
	case 422:
		errores := map[string][]string{}
		if raw, ok := generic["errores"].(map[string]any); ok {
			for k, v := range raw {
				if arr, ok := v.([]any); ok {
					for _, s := range arr {
						if str, ok := s.(string); ok {
							errores[k] = append(errores[k], str)
						}
					}
				}
			}
		}
		return nil, &ValidationError{
			APIError: APIError{Status: 422, Mensaje: mensaje},
			Errores:  errores,
		}
	case 429:
		plan, _ := generic["mejora_plan"].(map[string]any)
		return nil, &LimitError{
			APIError:   APIError{Status: 429, Mensaje: mensaje, CodigoError: "limite_alcanzado"},
			MejoraPlan: plan,
		}
	default:
		return nil, &APIError{Status: resp.StatusCode, Mensaje: mensaje, CodigoError: codError}
	}
}

// Helpers genéricos
func Get[T any](c *Client, path string) (T, error) {
	var result T
	body, err := c.Request("GET", path, nil, false)
	if err != nil {
		return result, err
	}
	defer body.Close()
	var wrapper Response[T]
	if err := json.NewDecoder(body).Decode(&wrapper); err != nil {
		return result, err
	}
	return wrapper.Datos, nil
}

func Post[T any](c *Client, path string, body any) (T, error) {
	var result T
	respBody, err := c.Request("POST", path, body, false)
	if err != nil {
		return result, err
	}
	defer respBody.Close()
	var wrapper Response[T]
	if err := json.NewDecoder(respBody).Decode(&wrapper); err != nil {
		return result, err
	}
	return wrapper.Datos, nil
}
```

---

## 2. Resources

`sunat/facturas.go`:

```go
package sunat

type FacturasResource struct {
	client *Client
}

type CrearFacturaInput struct {
	Serie            string   `json:"serie"`
	FechaEmision     string   `json:"fecha_emision"`
	TipoOperacion    string   `json:"tipo_operacion,omitempty"`
	TipoMoneda       string   `json:"tipo_moneda,omitempty"`
	FormaPago        string   `json:"forma_pago,omitempty"`
	Cliente          Cliente  `json:"cliente"`
	Items            []Item   `json:"items"`
	EnviarAutomatico *bool    `json:"enviar_automatico,omitempty"`
	Observacion      string   `json:"observacion,omitempty"`
}

type Cliente struct {
	TipoDoc     string `json:"tipo_doc"`
	NumDoc      string `json:"num_doc"`
	RazonSocial string `json:"razon_social"`
	Direccion   string `json:"direccion,omitempty"`
	Email       string `json:"email,omitempty"`
	Telefono    string `json:"telefono,omitempty"`
}

type Item struct {
	Codigo         string  `json:"codigo"`
	Descripcion    string  `json:"descripcion"`
	Unidad         string  `json:"unidad"`
	Cantidad       float64 `json:"cantidad"`
	PrecioUnitario float64 `json:"precio_unitario"`
	TipAfeIGV      string  `json:"tip_afe_igv"`
}

type Factura struct {
	ID             int       `json:"id"`
	Serie          string    `json:"serie"`
	Correlativo    int       `json:"correlativo"`
	NumeroCompleto string    `json:"numero_completo"`
	FechaEmision   string    `json:"fecha_emision"`
	Cliente        Cliente   `json:"cliente"`
	Items          []Item    `json:"items"`
	Sunat          SunatInfo `json:"sunat"`
	Totales        Totales   `json:"totales"`
}

type SunatInfo struct {
	Estado      string  `json:"estado"`
	Codigo      *string `json:"codigo"`
	Descripcion *string `json:"descripcion"`
	HashCpe     *string `json:"hash_cpe"`
}

type Totales struct {
	Gravadas       float64 `json:"gravadas"`
	IGV            float64 `json:"igv"`
	TotalImpuestos float64 `json:"total_impuestos"`
	ValorVenta     float64 `json:"valor_venta"`
	SubTotal       float64 `json:"sub_total"`
	Total          float64 `json:"total"`
}

func (r *FacturasResource) Crear(input CrearFacturaInput) (*Factura, error) {
	f, err := Post[Factura](r.client, "/facturas", input)
	if err != nil {
		return nil, err
	}
	return &f, nil
}

func (r *FacturasResource) Ver(id int) (*Factura, error) {
	f, err := Get[Factura](r.client, fmt.Sprintf("/facturas/%d", id))
	if err != nil {
		return nil, err
	}
	return &f, nil
}

func (r *FacturasResource) Enviar(id int) (*Factura, error) {
	f, err := Post[Factura](r.client, fmt.Sprintf("/facturas/%d/enviar", id), map[string]any{})
	if err != nil {
		return nil, err
	}
	return &f, nil
}

func (r *FacturasResource) PDF(id int, formato string) ([]byte, error) {
	if formato == "" {
		formato = "a4"
	}
	body, err := r.client.Request("GET", fmt.Sprintf("/facturas/%d/pdf?format=%s", id, formato), nil, true)
	if err != nil {
		return nil, err
	}
	defer body.Close()
	return io.ReadAll(body)
}
```

---

## 3. Uso (chi/gin/fiber/net/http)

```go
package main

import (
	"encoding/json"
	"errors"
	"net/http"
	"os"
	"your-app/sunat"
)

func main() {
	client := sunat.New(sunat.Config{
		BaseURL:   os.Getenv("SUNAT_BASE_URL"),
		APIKey:    os.Getenv("SUNAT_API_KEY"),
		APISecret: os.Getenv("SUNAT_API_SECRET"),
	})

	http.HandleFunc("POST /facturas", func(w http.ResponseWriter, r *http.Request) {
		var input sunat.CrearFacturaInput
		if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
			http.Error(w, err.Error(), 400)
			return
		}

		factura, err := client.Facturas.Crear(input)
		if err != nil {
			var valErr *sunat.ValidationError
			if errors.As(err, &valErr) {
				w.WriteHeader(422)
				json.NewEncoder(w).Encode(map[string]any{"errores": valErr.Errores})
				return
			}
			http.Error(w, err.Error(), 500)
			return
		}

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(201)
		json.NewEncoder(w).Encode(map[string]any{"ok": true, "factura": factura})
	})

	http.ListenAndServe(":8080", nil)
}
```

---

## 4. Webhook handler

```go
http.HandleFunc("POST /sunat/webhook", func(w http.ResponseWriter, r *http.Request) {
	var event struct {
		Event    string         `json:"event"`
		Model    string         `json:"model"`
		ID       int            `json:"id"`
		Data     map[string]any `json:"data"`
		TenantID int            `json:"tenant_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&event); err != nil {
		http.Error(w, err.Error(), 400)
		return
	}

	switch event.Event {
	case "document.sent":
		if event.Data["sunat_status"] == "aceptado" {
			// actualizar BD, enviar email, etc.
		}
	case "document.rejected":
		// alertar
	}

	w.WriteHeader(200)
	json.NewEncoder(w).Encode(map[string]bool{"ok": true})
})
```

---

## 5. Testing

```go
package sunat_test

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"your-app/sunat"
)

func TestCrearFactura(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(201)
		w.Write([]byte(`{"estado":"exito","mensaje":"Creado","datos":{"id":1,"numero_completo":"F001-000001"}}`))
	}))
	defer server.Close()

	client := sunat.New(sunat.Config{
		BaseURL: server.URL, APIKey: "test", APISecret: "test",
	})

	factura, err := client.Facturas.Crear(sunat.CrearFacturaInput{
		Serie: "F001", FechaEmision: "2026-04-19",
		Cliente: sunat.Cliente{TipoDoc: "6", NumDoc: "20000000001", RazonSocial: "TEST"},
		Items:   []sunat.Item{{Codigo: "P", Descripcion: "X", Unidad: "NIU", Cantidad: 1, PrecioUnitario: 100, TipAfeIGV: "10"}},
	})
	if err != nil {
		t.Fatal(err)
	}
	if factura.ID != 1 {
		t.Errorf("expected ID 1, got %d", factura.ID)
	}
}
```

---

## 6. Estructura

```
sunat/
├── client.go
├── facturas.go
├── boletas.go
├── notas.go
├── empresa.go
└── client_test.go
```

---

## 7. Env

```
SUNAT_BASE_URL=https://api.kodevo.es/sunat-api/api/v1
SUNAT_API_KEY=xxx
SUNAT_API_SECRET=yyy
```

Con `github.com/joho/godotenv` o `os.Getenv`.

---

## 8. go.mod

```go
module your-app

go 1.22

// Sin dependencias externas — todo con stdlib
```
